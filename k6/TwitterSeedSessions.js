import http from 'k6/http';
import { check } from 'k6';
import exec from 'k6/execution';
import { textSummary } from 'https://jslib.k6.io/k6-summary/0.0.1/index.js';
import sql from 'k6/x/sql';
import driver from 'k6/x/sql/driver/mysql';

/* =========================================================================
   🔧 CONFIGURATION & PARAMS
   ========================================================================= */

const BASE_URL = __ENV.BASE_URL || 'https://localhost';
const TARGET_USERS = parseInt(__ENV.TARGET_USERS || '5000', 10);

const MySQL_USER = __ENV.MYSQL_USER || 'app';
const MySQL_PASSWORD = __ENV.MYSQL_PASSWORD || '!ChangeMe!';
const MySQL_IP = __ENV.MYSQL_IP || '127.0.0.1';
const MySQL_PORT = __ENV.MYSQL_PORT || '3306';
const MySQL_DATABASE = __ENV.MYSQL_DATABASE || 'app';

/* =========================================================================
   📊 K6 OPTIONS (5 VUs Parallel Session Warmer)
   ========================================================================= */

export const options = {
  insecureSkipTLSVerify: true,
  setupTimeout: '10m',

  scenarios: {
    seed_sessions: {
      executor: 'shared-iterations',
      vus: 5, // Exactly 5 parallel workers to match FrankenPHP worker capacity
      iterations: TARGET_USERS,
      maxDuration: '2h',
    },
  },

  thresholds: {
    http_req_failed: ['rate<0.05'], // Allow < 5% error margin
    'http_req_duration{endpoint:Login}': ['p(95)<5000'],
  },
};

/* =========================================================================
   🛠 UTILITIES
   ========================================================================= */

function asciiToString(bytes) {
  if (!bytes) return '';
  if (typeof bytes === 'string') return bytes;
  return String.fromCharCode(...bytes);
}

/* =========================================================================
   📦 SETUP (Find users needing active sessions)
   ========================================================================= */

export function setup() {
  console.log(`\x1b[34m[SEED SETUP]\x1b[0m Connecting to MySQL at ${MySQL_IP}:${MySQL_PORT}...`);
  const db = sql.open(
    driver,
    `${MySQL_USER}:${MySQL_PASSWORD}@tcp(${MySQL_IP}:${MySQL_PORT})/${MySQL_DATABASE}`
  );

  // 1. Check existing active sessions
  const activeCountRows = db.query(
    'SELECT COUNT(DISTINCT username) as cnt FROM app.refresh_tokens WHERE valid > NOW();'
  );
  let activeBefore = 0;
  if (activeCountRows && activeCountRows.length > 0) {
    activeBefore = Number(activeCountRows[0].cnt);
  }

  console.log(
    `\x1b[34m[SEED SETUP]\x1b[0m Current active sessions in DB: \x1b[32m${activeBefore}\x1b[0m.`
  );
  console.log(
    `\x1b[34m[SEED SETUP]\x1b[0m Querying up to \x1b[33m${TARGET_USERS}\x1b[0m unauthenticated users from app.users...`
  );

  // 2. Select users that do not have an active refresh token
  const unauthRows = db.query(`
    SELECT email FROM app.users 
    WHERE email NOT IN (
      SELECT DISTINCT username FROM app.refresh_tokens WHERE valid > NOW()
    ) 
    ORDER BY RAND() 
    LIMIT ${TARGET_USERS};
  `);

  const emails = [];
  for (const row of unauthRows) {
    emails.push(asciiToString(row.email));
  }

  db.close();

  if (emails.length === 0) {
    console.log(
      `\x1b[32m[SEED SETUP]\x1b[0m All users in database already have active refresh tokens! Nothing to seed.`
    );
  } else {
    console.log(
      `\x1b[32m[SEED SETUP]\x1b[0m Found \x1b[36m${emails.length}\x1b[0m users to authenticate in 5 parallel streams.`
    );
  }

  return {
    emails,
    activeBefore,
    targetCount: TARGET_USERS,
  };
}

/* =========================================================================
   🔑 MAIN LOGIN TASK (Shared iterations across 5 VUs)
   ========================================================================= */

export default function (data) {
  const iteration = exec.scenario.iterationInTest;
  if (!data.emails || iteration >= data.emails.length) {
    return;
  }

  const email = data.emails[iteration];

  const payload = JSON.stringify({
    email: email,
    password: 'Pa$$word1',
  });

  const params = {
    headers: {
      'Content-Type': 'application/json',
    },
    tags: {
      endpoint: 'Login',
      name: 'POST /api/token',
    },
  };

  const res = http.post(`${BASE_URL}/api/token`, payload, params);

  check(res, {
    'login status is 200': (r) => r.status === 200,
    'refresh token received': (r) => !!r.json('refresh_token'),
  });

  // Progress logging
  if (iteration > 0 && iteration % 100 === 0) {
    const total = data.emails.length;
    const pct = ((iteration / total) * 100).toFixed(1);
    console.log(
      `\x1b[34m[PROGRESS]\x1b[0m Logged in \x1b[32m${iteration}/${total}\x1b[0m users (${pct}% completed)...`
    );
  }
}

/* =========================================================================
   🏁 TEARDOWN (Final validation)
   ========================================================================= */

export function teardown(data) {
  const db = sql.open(
    driver,
    `${MySQL_USER}:${MySQL_PASSWORD}@tcp(${MySQL_IP}:${MySQL_PORT})/${MySQL_DATABASE}`
  );

  const activeCountRows = db.query(
    'SELECT COUNT(DISTINCT username) as cnt FROM app.refresh_tokens WHERE valid > NOW();'
  );
  let activeAfter = 0;
  if (activeCountRows && activeCountRows.length > 0) {
    activeAfter = Number(activeCountRows[0].cnt);
  }
  db.close();

  const newlyAdded = activeAfter - data.activeBefore;
  console.log('\n===============================================================');
  console.log(`\x1b[32m#SEEDING COMPLETE#\x1b[0m`);
  console.log(`  Initial Active Sessions : ${data.activeBefore}`);
  console.log(`  Newly Activated Users   : \x1b[32m+${newlyAdded}\x1b[0m`);
  console.log(`  Total Active Pool in DB : \x1b[36m${activeAfter}\x1b[0m users (valid for 7 days)`);
  console.log('===============================================================\n');
}

export function handleSummary(data) {
  return {
    stdout: textSummary(data, {
      indent: ' ',
      enableColors: true,
    }),
  };
}

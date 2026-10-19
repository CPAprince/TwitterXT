import http from 'k6/http';
import { check, group, sleep } from 'k6';
import { textSummary } from 'https://jslib.k6.io/k6-summary/0.0.1/index.js';
import sql from "k6/x/sql";
import driver from "k6/x/sql/driver/mysql";

/* =========================
   🔧 CONFIGURATION
========================= */

// The second argument is a MySQL connection string, e.g.
// myuser:mypass@tcp(127.0.0.1:3306)/mydb
const MySQL_USER = "app";
const MySQL_PASSWORD = "!ChangeMe!";
const MySQL_IP = "127.0.0.1";
const MySQL_PORT = "3306";
const MySQL_DATABASE = "app";


const db = sql.open(driver, `${MySQL_USER}:${MySQL_PASSWORD}@tcp(${MySQL_IP}:${MySQL_PORT})/${MySQL_DATABASE}`);

const BASE_URL = 'https://localhost';

const userLoginShare = 100; // % users that login
const maxFeedPages = parseInt(__ENV.FEED_PAGES || '15', 10);    // max view depth viewing main feed
const scrollProbability = 0.9; // scroll posibility

const FEED_LIMIT = 50;
const THINK_TIME_MIN = 0; // seconds
const THINK_TIME_MAX = 0; // seconds

/* =========================
   📊 K6 OPTIONS
========================= */

const endpoints = [
  'tweets_page1',
  'tweets_page2',
  'tweets_page3',
  'tweets_page4plus',
  'ProfileNameBio',
  'ProfileFeed',
  'Like',
  'Login',
  'CreateTweet',
];

export const options = {
  insecureSkipTLSVerify: true,



  stages: [
  { duration: '10s', target: 6 },
   { duration: '1m', target: 6 },
    { duration: '30m', target: 6 },
    { duration: '30m', target: 6 },
    { duration: '1m', target: 6 },
  ],

thresholds: {
      http_req_failed: ['rate<0.01'],
	  'http_req_waiting': ['p(95)<10000'],
    ...endpoints.reduce(
      (acc, endpoint) => ({
        ...acc,
        [`http_req_duration{endpoint:${endpoint}}`]: ['p(95)<10000'],
       // [`http_req_waiting{endpoint:${endpoint}}`]: ['p(95)<10000'],
        [`http_reqs{endpoint:${endpoint}}`]: ['count>=0'],
      }),
      {}
    ),
},
};

/* ================================
    📦 TEST DATA LOAD from MYSQL
=================================== */

let rows


function utf8BytesToString(bytes) {
  let out = '';
  let i = 0;

  while (i < bytes.length) {
    const c = bytes[i++];

    if (c < 128) {
      out += String.fromCharCode(c);
    } else if (c > 191 && c < 224) {
      const c2 = bytes[i++];
      out += String.fromCharCode(((c & 31) << 6) | (c2 & 63));
    } else if (c > 223 && c < 240) {
      const c2 = bytes[i++];
      const c3 = bytes[i++];
      out += String.fromCharCode(
        ((c & 15) << 12) | ((c2 & 63) << 6) | (c3 & 63)
      );
    } else {
      const c2 = bytes[i++];
      const c3 = bytes[i++];
      const c4 = bytes[i++];

      let codepoint =
        ((c & 7) << 18) |
        ((c2 & 63) << 12) |
        ((c3 & 63) << 6) |
        (c4 & 63);

      codepoint -= 0x10000;
      out += String.fromCharCode(
        0xd800 + (codepoint >> 10),
        0xdc00 + (codepoint & 0x3ff)
      );
    }
  }

  return out;
}

let emailsAvailable = [];

rows = db.query("SELECT email FROM app.users limit 50000;");
  for (const row of rows) {
    // Convert array of ASCII integers into strings. See https://github.com/grafana/xk6-sql/issues/12
   // console.log(`${String.fromCharCode(...row.email)}`);
	emailsAvailable.push(String.fromCharCode(...row.email));
  };
/*  console.log("\n\n==================================================\n\n");
  console.log(emailsAvailable);
  console.log("\n\n==================================================\n\n");*/

let profilesAvailable = [];
  
rows = db.query("SELECT distinct BIN_TO_UUID(user_id) as profile_uuid FROM app.tweets limit 10000;");
  for (const row of rows) {
    // Convert array of ASCII integers into strings. See https://github.com/grafana/xk6-sql/issues/12
   // console.log(`${String.fromCharCode(...row.email)}`);
	profilesAvailable.push(String.fromCharCode(...row.profile_uuid));
  };
 /* console.log("\n\n==================================================\n\n");
  console.log(profilesAvailable);
  console.log("\n\n==================================================\n\n");  */
  
let tweetsAvailable = [];
  
rows = db.query("SELECT BIN_TO_UUID(id) as tweet_id FROM app.tweets limit 20000;");
  for (const row of rows) {
    // Convert array of ASCII integers into strings. See https://github.com/grafana/xk6-sql/issues/12
   // console.log(`${String.fromCharCode(...row.email)}`);
	tweetsAvailable.push(String.fromCharCode(...row.tweet_id));
  };
 /* console.log("\n\n==================================================\n\n");
  console.log(tweetsAvailable);
  console.log("\n\n==================================================\n\n");  */
  
 /* console.log("Emails:" + emailsAvailable.length + "\n");
  console.log("ProfilesWithTweets:" + profilesAvailable.length + "\n");
  console.log("TweetsToLike:" + tweetsAvailable.length + "\n");*/

let tweets4postingAvailable = [];  

rows = db.query("SELECT content FROM app.tweets where created_at<='2026-01-25' order by rand() limit 100000;");
  for (const row of rows) {
    // Convert array of ASCII integers into strings. See https://github.com/grafana/xk6-sql/issues/12
   // console.log(`${String.fromCharCode(...row.email)}`);
//	tweets4postingAvailable.push(String.fromCharCode(...row.content));
	tweets4postingAvailable.push(utf8BytesToString(row.content));
	//console.log(utf8BytesToString(row.content));
  };
  
    if(__VU===1){
  console.log("\x1b[33m#TEST DATA#\t" +"\x1b[32mEmails: \x1b[36m" + emailsAvailable.length + " \x1b[33m| " + "\x1b[32mProfilesWithTweets: \x1b[36m" + profilesAvailable.length + " \x1b[33m| " + "\x1b[32mTweetsToLike: \x1b[36m" + tweetsAvailable.length + " \x1b[33m| " + "\x1b[32mTweetsToPost: \x1b[36m" + tweets4postingAvailable.length + "\x1b[0m  ");
  }
  
  export function teardown() {
  db.close();
}
  
/* =========================
   🛠 HELPERS
========================= */

function randomItem(arr) {
  return arr[Math.floor(Math.random() * arr.length)];
}

function randomSleep() {
  const t = THINK_TIME_MIN + Math.random() * (THINK_TIME_MAX - THINK_TIME_MIN);
  sleep(t);
}

function authHeaders(jwt) {
  return jwt
    ? { headers: { Authorization: `Bearer ${jwt}` } }
    : {};
}

function shouldLogin() {
  return Math.random() * 100 < userLoginShare;
}

/* =========================
   🔐 AUTH
========================= */

function login() {
  const payload = JSON.stringify({
    email: randomItem(emailsAvailable),
    password: 'Pa$$word1',
  });

  const params = {
    headers: {
      'Content-Type': 'application/json',
    },
	tags: {
      endpoint: 'Login',
    },
  };

  return group('POST /api/token (login)', () => {
    const res = http.post(`${BASE_URL}/api/token`, payload, params);

    check(res, {
      'login status is 200': (r) => r.status === 200,
      'JWT received': (r) => !!r.json('token'),
    });

    return res.json('token');
  });
}

/* =========================
   📰 MAIN FEED (with cohorts)
========================= */

function mainFeed(jwt) {
  let page = 1;

  while (page <= maxFeedPages) {
    let cohort;

    if (page === 1) cohort = 'page=1';
    else if (page === 2) cohort = 'page=2';
    else if (page === 3) cohort = 'page=3';
    else cohort = 'page>=4';

    const endpointTag =
      cohort === 'page=1' ? 'tweets_page1' :
      cohort === 'page=2' ? 'tweets_page2' :
      cohort === 'page=3' ? 'tweets_page3' :
                             'tweets_page4plus';

    group(`GET /api/tweets (${cohort})`, () => {
      http.get(
        `${BASE_URL}/api/tweets?limit=${FEED_LIMIT}&page=${page}`,
        {
          ...authHeaders(jwt),
          tags: {
            endpoint: endpointTag,
          },
        }
      );
    });

    if (page === maxFeedPages || Math.random() > scrollProbability) break;
    page++;
    randomSleep();
  }
}

/* =========================
   👤 PROFILE
========================= */

function getProfile(jwt) {
  const profileId = randomItem(profilesAvailable);

  group('GET /api/profiles/{id}', () => {
    http.get(
      `${BASE_URL}/api/profiles/${profileId}`,
		  {
      ...authHeaders(jwt),
	            tags: {
            endpoint: "ProfileNameBio",
          },
		  }
    );
  });

  group('GET /api/profiles/{id}/tweets', () => {
    http.get(
      `${BASE_URL}/api/profiles/${profileId}/tweets?limit=${FEED_LIMIT}&page=1`,
		  {
      ...authHeaders(jwt),
	  	        tags: {
            endpoint: "ProfileFeed",
          },
		  }
    );
  });
}

/* =========================
   ❤️ LIKE
========================= */

function toggleLike(jwt) {
  if (!jwt) return;

  const tweetId = randomItem(tweetsAvailable);

  group('POST /api/tweets/{id}/likes', () => {
    http.post(
      `${BASE_URL}/api/tweets/${tweetId}/likes/toggle`,
      null,{
      ...authHeaders(jwt),
	  	            tags: {
            endpoint: "Like",
          },
	  }
    );
  });
}

/* =========================
   ✍️ CREATE TWEET
========================= */

function createTweet(jwt) {
  if (!jwt) return;
  
    const tweetcontent = randomItem(tweets4postingAvailable);
	//console.log(tweetcontent);

  const payload = JSON.stringify({
    content: tweetcontent,
  });

  const params = {
    headers: {
      Authorization: `Bearer ${jwt}`,
      'Content-Type': 'application/json',
    },
    tags: {
      endpoint: 'CreateTweet',
    },
  };

  group('POST /api/tweets', () => {
    http.post(`${BASE_URL}/api/tweets`, payload, params);
  });
}

/* =========================
   🚶 USER JOURNEY
========================= */

export default function () {
  let jwt = null;

    jwt = login();
	
 const posts = jwt ? Math.floor(Math.random() * 47+3) : 0;	
  for (let i = 0; i < posts; i++) {
  createTweet(jwt);
    randomSleep();
  }
  
}

export function handleSummary(data) {
  return {
    stdout: textSummary(data, {
      indent: ' ',
      enableColors: true,
    }),
  };
}
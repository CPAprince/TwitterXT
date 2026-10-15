<?php

declare(strict_types=1);

namespace Twitter\Tweet\Infrastructure\Moderation;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Redis;
use RuntimeException;
use Twitter\Tweet\Application\Moderation\ModerationConfig;
use Twitter\Tweet\Application\Moderation\ModerationRateLimiterInterface;
use Twitter\Tweet\Application\Moderation\ModerationRateLimitResult;

final readonly class RedisModerationRateLimiter implements ModerationRateLimiterInterface
{
    private const LUA_SCRIPT = <<<'LUA'
local reqMinuteKey = KEYS[1]
local tokMinuteKey = KEYS[2]
local reqDayKey    = KEYS[3]

local rpmLimit     = tonumber(ARGV[1])
local tpmLimit     = tonumber(ARGV[2])
local rpdLimit     = tonumber(ARGV[3])
local batchTokens  = tonumber(ARGV[4])

local reqMinuteTtl = tonumber(ARGV[5])
local tokMinuteTtl = tonumber(ARGV[6])
local reqDayTtl    = tonumber(ARGV[7])

local reqMinute = tonumber(redis.call('GET', reqMinuteKey) or '0')
local tokMinute = tonumber(redis.call('GET', tokMinuteKey) or '0')
local reqDay    = tonumber(redis.call('GET', reqDayKey) or '0')

if reqMinute + 1 > rpmLimit then
    return {0, 'rpm_exceeded'}
end

if tokMinute + batchTokens > tpmLimit then
    return {0, 'tpm_exceeded'}
end

if reqDay + 1 > rpdLimit then
    return {0, 'rpd_exceeded'}
end

reqMinute = redis.call('INCRBY', reqMinuteKey, 1)
tokMinute = redis.call('INCRBY', tokMinuteKey, batchTokens)
reqDay = redis.call('INCRBY', reqDayKey, 1)

if reqMinute == 1 then
    redis.call('EXPIRE', reqMinuteKey, reqMinuteTtl)
end

if tokMinute == batchTokens then
    redis.call('EXPIRE', tokMinuteKey, tokMinuteTtl)
end

if reqDay == 1 then
    redis.call('EXPIRE', reqDayKey, reqDayTtl)
end

return {1, 'allowed'}
LUA;

    public function __construct(
        private Redis $redis,
        private ModerationConfig $config,
    ) {}

    public function reserveCapacity(int $batchEstimatedTokens): ModerationRateLimitResult
    {
        if ($batchEstimatedTokens <= 0) {
            throw new InvalidArgumentException('Batch estimated tokens must be greater than 0.');
        }

        [$reqMinuteKey, $tokMinuteKey, $reqDayKey] = $this->buildKeys();

        $reqMinuteTtl = $this->secondsUntilNextMinute();
        $tokMinuteTtl = $reqMinuteTtl;
        $reqDayTtl = $this->secondsUntilNextDay();

        $result = $this->redis->eval(
            self::LUA_SCRIPT,
            [
                $reqMinuteKey,
                $tokMinuteKey,
                $reqDayKey,
                $this->config->limitRpm,
                $this->config->limitTpm,
                $this->config->limitRpd,
                $batchEstimatedTokens,
                $reqMinuteTtl,
                $tokMinuteTtl,
                $reqDayTtl,
            ],
            3
        );

        if (!is_array($result) || count($result) < 2) {
            throw new RuntimeException('Unexpected Redis rate limiter response.');
        }

        $allowed = 1 === (int) $result[0];
        $reason = (string) $result[1];

        if ($allowed) {
            return ModerationRateLimitResult::allowed();
        }

        return ModerationRateLimitResult::denied($reason);
    }

    /**
     * @return array{string, string, string}
     */
    private function buildKeys(): array
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $minuteSuffix = $now->format('YmdHi');
        $daySuffix = $now->format('Ymd');

        return [
            $this->config->redisRateReqPrefix.$minuteSuffix,
            $this->config->redisRateTokPrefix.$minuteSuffix,
            $this->config->redisRateReqDayPrefix.$daySuffix,
        ];
    }

    private function secondsUntilNextMinute(): int
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $nextMinute = $now
            ->setTime(
                (int) $now->format('H'),
                (int) $now->format('i'),
                0
            )
            ->modify('+1 minute');

        return max(1, $nextMinute->getTimestamp() - $now->getTimestamp());
    }

    private function secondsUntilNextDay(): int
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $nextDay = $now->modify('tomorrow')->setTime(0, 0, 0);

        return max(1, $nextDay->getTimestamp() - $now->getTimestamp());
    }
}

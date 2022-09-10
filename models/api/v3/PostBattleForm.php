<?php

/**
 * @copyright Copyright (C) 2015-2022 AIZAWA Hina
 * @license https://github.com/fetus-hina/stat.ink/blob/master/LICENSE MIT
 * @author AIZAWA Hina <hina@fetus.jp>
 */

namespace app\models\api\v3;

use Yii;
use app\components\behaviors\TrimAttributesBehavior;
use app\components\db\Connection;
use app\components\helpers\CriticalSection;
use app\components\helpers\UuidRegexp;
use app\components\helpers\db\Now;
use app\components\validators\KeyValidator;
use app\models\Agent;
use app\models\Battle3;
use app\models\Lobby3;
use app\models\Map3;
use app\models\Map3Alias;
use app\models\Rank3;
use app\models\Result3;
use app\models\Rule3;
use app\models\SplatoonVersion3;
use app\models\User;
use app\models\Weapon3;
use app\models\Weapon3Alias;
use jp3cki\uuid\Uuid;
use yii\base\InvalidConfigException;
use yii\base\Model;
use yii\db\ActiveRecord;
use yii\helpers\Json;

/**
 * @property-read Battle3|null $sameBattle
 * @property-read bool $isTest
 */
final class PostBattleForm extends Model
{
    public const SAME_BATTLE_THRESHOLD_TIME = 86400;

    public $test;

    public $uuid;
    public $lobby;
    public $rule;
    public $stage;
    public $weapon;
    public $result;
    public $knockout;
    public $rank_in_team;
    public $kill;
    public $assist;
    public $kill_or_assist;
    public $death;
    public $special;
    public $inked;
    public $our_team_inked;
    public $their_team_inked;
    public $our_team_percent;
    public $their_team_percent;
    public $our_team_count;
    public $their_team_count;
    public $level_before;
    public $level_after;
    public $rank_before;
    public $rank_before_s_plus;
    public $rank_before_exp;
    public $rank_after;
    public $rank_after_s_plus;
    public $rank_after_exp;
    public $cash_before;
    public $cash_after;
    public $note;
    public $private_note;
    public $link_url;
    public $agent;
    public $agent_version;
    public $automated;
    public $start_at;
    public $end_at;

    public function behaviors()
    {
        return [
            [
                'class' => TrimAttributesBehavior::class,
                'targets' => array_keys($this->attributes),
            ],
        ];
    }

    public function rules()
    {
        return [
            [['uuid', 'lobby', 'rule', 'stage', 'weapon', 'result', 'rank_before', 'rank_after', 'note'], 'string'],
            [['private_note', 'link_url', 'agent', 'agent_version'], 'string'],

            [['uuid'], 'match', 'pattern' => UuidRegexp::get(true)],
            [['result'], 'in', 'range' => ['win', 'lose', 'draw']],
            [['link_url'], 'url',
                'validSchemes' => ['http', 'https'],
                'defaultScheme' => null,
                'enableIDN' => false,
            ],
            [['agent'], 'string', 'max' => 64],
            [['agent_version'], 'string', 'max' => 255],
            [['agent', 'agent_version'], 'required',
                'when' => fn () => \trim((string)$this->agent) !== '' || \trim((string)$this->agent_version) !== '',
            ],
            [['test', 'knockout', 'automated'], 'in',
                'range' => ['yes', 'no', true, false],
                'strict' => true,
            ],
            [['rank_in_team'], 'integer', 'min' => 1, 'max' => 4],
            [['kill', 'assist', 'kill_or_assist', 'death', 'special'], 'integer', 'min' => 0, 'max' => 99],
            [['inked'], 'integer', 'min' => 0, 'max' => 9999],
            [['our_team_inked', 'their_team_inked'], 'integer', 'min' => 0, 'max' => 99999],
            [['our_team_percent', 'their_team_percent'], 'number', 'min' => 0, 'max' => 100],
            [['our_team_count', 'their_team_count'], 'integer', 'min' => 0, 'max' => 100],
            [['level_before', 'level_after'], 'integer', 'min' => 1, 'max' => 99],
            [['rank_before_s_plus', 'rank_after_s_plus'], 'integer', 'min' => 0, 'max' => 50],
            [['rank_before_exp', 'rank_after_exp'], 'integer', 'min' => 0],
            [['cash_before', 'cash_after'], 'integer', 'min' => 0, 'max' => 9999999],
            [['start_at', 'end_at'], 'integer',
                'min' => \strtotime('2022-01-01T00:00:00+00:00'),
                'max' => time() + 3600,
            ],

            [['lobby'], KeyValidator::class, 'modelClass' => Lobby3::class],
            [['rule'], KeyValidator::class, 'modelClass' => Rule3::class],
            [['stage'], KeyValidator::class,
                'modelClass' => Map3::class,
                'aliasClass' => Map3Alias::class,
            ],
            [['weapon'], KeyValidator::class,
                'modelClass' => Weapon3::class,
                'aliasClass' => Weapon3Alias::class,
            ],
            [['rank_before', 'rank_after'], KeyValidator::class, 'modelClass' => Rank3::class],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
        ];
    }

    public function getSameBattle(): ?Battle3
    {
        if (
            !\is_string($this->uuid) ||
            $this->uuid === ''
        ) {
            return null;
        }

        if (!$user = Yii::$app->user->identity) {
            return null;
        }

        $t = (int)($_SERVER['REQUEST_TIME'] ?? time());
        return Battle3::find()
            ->where([
                'user_id' => $user->id,
                'client_uuid' => $this->uuid,
            ])
            ->andWhere(
                ['>=', 'created_at', \gmdate('Y-m-d\TH:i:sP', $t - self::SAME_BATTLE_THRESHOLD_TIME)]
            )
            ->limit(1)
            ->one();
    }

    public function getIsTest(): bool
    {
        return $this->test === 'yes' || $this->test === true;
    }

    /**
     * @return Battle3|bool|null
     */
    public function save()
    {
        if (!$this->validate()) {
            return null;
        }

        if ($this->getIsTest()) {
            return true;
        }

        if (!$lock = CriticalSection::lock($this->getCriticalSectionId(), 60)) {
            $this->addError('_system', 'Failed to get lock. System busy. Try again.');
            return null;
        }

        try {
            return $this->getSameBattle() ?? $this->saveNewBattleRelation();
        } finally {
            unset($lock);
        }
    }

    private function getCriticalSectionId(): string
    {
        $values = [
            'class' => __CLASS__,
            'user' => Yii::$app->user->id,
            'version' => 1,
        ];
        \asort($values);
        return \rtrim(
            \base64_encode(
                \hash_hmac(
                    'sha256',
                    Json::encode($values),
                    (string)Yii::getAlias('@app'),
                    true,
                ),
            ),
            '=',
        );
    }

    private function saveNewBattleRelation(): ?Battle3
    {
        try {
            $connection = Yii::$app->db;
            if (!$connection instanceof Connection) {
                throw new InvalidConfigException();
            }

            return $connection->transactionEx(function (Connection $connection): ?Battle3 {
                if (!$battle = $this->saveNewBattle()) {
                    return null;
                }

                // TODO: more data

                return $battle;
            });
        } catch (Throwable $e) {
            $this->addError(
                '_system',
                vsprintf('Failed to store your battle (internal error), %s', [
                    \get_class($e),
                ]),
            );
            return null;
        }
    }

    private function saveNewBattle(): ?Battle3
    {
        $uuid = (string)Uuid::v4();
        $model = Yii::createObject([
            'class' => Battle3::class,
            'uuid' => $uuid,
            'client_uuid' => $this->uuid ?: $uuid,
            'user_id' => Yii::$app->user->id,
            'lobby_id' => self::key2id($this->lobby, Lobby3::class),
            'rule_id' => self::key2id($this->rule, Rule3::class),
            'map_id' => self::key2id($this->stage, Map3::class, Map3Alias::class, 'map_id'),
            'weapon_id' => self::key2id($this->weapon, Weapon3::class, Weapon3Alias::class, 'weapon_id'),
            'result_id' => self::key2id($this->result, Result3::class),
            'is_knockout' => self::boolVal($this->knockout),
            'rank_in_team' => self::intVal($this->rank_in_team),
            'kill' => self::intVal($this->kill),
            'assist' => self::intVal($this->assist),
            'kill_or_assist' => self::intVal($this->kill_or_assist), // あとで確認
            'death' => self::intVal($this->death),
            'special' => self::intVal($this->special),
            'inked' => self::intVal($this->inked),
            'our_team_inked' => self::intVal($this->our_team_inked),
            'their_team_inked' => self::intVal($this->their_team_inked),
            'our_team_percent' => self::floatVal($this->our_team_percent),
            'their_team_percent' => self::floatVal($this->their_team_percent),
            'our_team_count' => self::intVal($this->our_team_count),
            'their_team_count' => self::intVal($this->their_team_count),
            'level_before' => self::intVal($this->level_before),
            'level_after' => self::intVal($this->level_after),
            'rank_before_id' => self::key2id($this->rank_before, Rank3::class),
            'rank_before_s_plus' => self::intVal($this->rank_before_s_plus),
            'rank_before_exp' => self::intVal($this->rank_before_exp),
            'rank_after_id' => self::key2id($this->rank_after, Rank3::class),
            'rank_after_s_plus' => self::intVal($this->rank_after_s_plus),
            'rank_after_exp' => self::intVal($this->rank_after_exp),
            'cash_before' => self::intVal($this->cash_before),
            'cash_after' => self::intVal($this->cash_after),
            'note' => self::strVal($this->note),
            'private_note' => self::strVal($this->private_note),
            'link_url' => self::strVal($this->link_url),
            'version_id' => self::gameVersion(self::intVal($this->start_at), self::intVal($this->end_at)),
            'agent_id' => self::userAgent($this->agent, $this->agent_version),
            'is_automated' => self::boolVal($this->automated) ?: false,
            'use_for_entire' => false, // あとで上書き
            'start_at' => self::tsVal(self::intVal($this->start_at)),
            'end_at' => self::tsVal(self::intVal($this->end_at) ?? time()),
            'period' => self::guessPeriod(self::intVal($this->start_at), self::intVal($this->end_at)),
            'remote_addr' => Yii::$app->request->getUserIP() ?? '127.0.0.2',
            'remote_port' => self::intVal($_SERVER['REMOTE_PORT'] ?? 0),
            'created_at' => self::now(),
            'updated_at' => self::now(),
        ]);

        // kill+assistが不明でkillとassistがわかっている
        if ($model->kill_or_assist === null && \is_int($model->kill) && \is_int($model->assist)) {
            $model->kill_or_assist = $model->kill + $model->assist;
        }

        // 設定された値から統計に使えそうか雑な判断をする
        $model->use_for_entire = self::isUsableForEntireStats($model);

        if (!$model->save()) {
            $this->addError('_system', vsprintf('Failed to store new battle, info=%s', [
                \base64_encode(Json::encode($model->getFirstErrors())),
            ]));
            return null;
        }

        return $model;
    }

    private static function userAgent(?string $agentName, ?string $agentVersion): ?int
    {
        $agentName = self::strVal($agentName);
        $agentVersion = self::strVal($agentVersion);
        if ($agentName === null || $agentVersion === null) {
            return null;
        }

        $model = Agent::find()
            ->andWhere([
                'name' => $agentName,
                'version' => $agentVersion,
            ])
            ->limit(1)
            ->one();
        if (!$model) {
            $model = Yii::createObject([
                'class' => Agent::class,
                'name' => $agentName,
                'version' => $agentVersion,
            ]);
            if (!$model->save()) {
                return null;
            }
        }

        return (int)$model->id;
    }

    private static function now(): Now
    {
        return Yii::createObject(Now::class);
    }

    private static function boolVal($value): ?bool
    {
        if (\is_bool($value)) {
            return $value;
        } elseif ($value === null || $value === '') {
            return null;
        } elseif ($value === 'yes') {
            return true;
        } elseif ($value === 'no') {
            return false;
        } else {
            return null;
        }
    }

    private static function intVal($value): ?int
    {
        $value = self::strVal($value);
        if ($value === null) {
            return null;
        }

        $value = \filter_var($value, FILTER_VALIDATE_INT);
        return \is_int($value) ? $value : null;
    }

    private static function floatVal($value): ?float
    {
        $value = self::strVal($value);
        if ($value === null) {
            return null;
        }

        $value = \filter_var($value, FILTER_VALIDATE_FLOAT);
        return \is_float($value) ? $value : null;
    }

    private static function strVal($value): ?string
    {
        if ($value === null || !\is_scalar($value)) {
            return null;
        }

        $value = \trim((string)$value);
        return $value !== '' ? $value : null;
    }

    private static function tsVal($value): ?string
    {
        $value = self::intVal($value);
        if (!\is_int($value)) {
            return null;
        }

        return \gmdate('Y-m-d\TH:i:sP', $value);
    }

    /**
     * @param string|null $value
     * @phpstan-param class-string<ActiveRecord> $modelClass
     * @phpstan-param class-string<ActiveRecord>|null $aliasClass
     */
    private static function key2id(
        $value,
        string $modelClass,
        ?string $aliasClass = null,
        ?string $aliasAttr = null
    ): ?int {
        $value = self::strVal($value);
        if ($value === null) {
            return null;
        }

        $model = $modelClass::find()->andWhere(['key' => $value])->limit(1)->one();
        if ($model) {
            return (int)$model->id;
        }

        if ($aliasClass && $aliasAttr) {
            $model = $aliasClass::find()->andWhere(['key' => $value])->limit(1)->one();
            if ($model && isset($model->$aliasAttr)) {
                return (int)$model->$aliasAttr;
            }
        }

        return null;
    }

    private static function gameVersion(?int $startAt, ?int $endAt): ?int
    {
        $startAt = self::guessStartAt($startAt, $endAt);
        $model = SplatoonVersion3::find()
            ->andWhere(['<=', 'release_at', self::tsVal($startAt)])
            ->orderBy(['release_at' => SORT_DESC])
            ->limit(1)
            ->one();
        return $model ? (int)$model->id : null;
    }

    private static function guessPeriod(?int $startAt, ?int $endAt): int
    {
        return self::timestamp2period(self::guessStartAt($startAt, $endAt));
    }

    private static function timestamp2period(int $ts): int
    {
        return (int)floor($ts / 7200);
    }

    private static function guessStartAt(?int $startAt, ?int $endAt): int
    {
        if (\is_int($startAt)) {
            return $startAt;
        }

        if (\is_int($endAt)) {
            // Guess the battle started 3 minutes before the end time.
            // It is clear if the battle is Turf War.
            // In other modes, the regulation time is 5 minutes,
            // but 3 minutes would be a reasonable estimate because of knockout possibilities.
            return $endAt - 180;
        }

        // Use 5 minutes before the current time as an estimated value if the time is unknown.
        return \time() - 300;
    }

    private static function isUsableForEntireStats(Battle3 $model): bool
    {
        if (
            !$model->is_automated ||
            !\is_int($model->start_at) ||
            $model->start_at < time() - 86400 ||
            !$model->lobby_id ||
            !$model->rule_id ||
            !$model->map_id ||
            !$model->weapon_id ||
            !$model->result_id
        ) {
            return false;
        }

        if (
            !($lobby = $model->lobby) ||
            !($result = $model->result)
        ) {
            return false;
        }

        if (
            $lobby->key === 'private' ||
            !$result->aggregatable
        ) {
            return false;
        }

        return true;
    }
}
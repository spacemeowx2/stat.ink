<?php

/**
 * @copyright Copyright (C) 2015-2022 AIZAWA Hina
 * @license https://github.com/fetus-hina/stat.ink/blob/master/LICENSE MIT
 * @author AIZAWA Hina <hina@fetus.jp>
 */

declare(strict_types=1);

namespace app\models\battle3FilterForm;

use Yii;
use app\models\Battle3FilterForm;
use app\models\Lobby3;
use app\models\LobbyGroup3;
use app\models\Mainweapon3;
use app\models\Map3;
use app\models\Result3;
use app\models\Rule3;
use app\models\RuleGroup3;
use app\models\Special3;
use app\models\Subweapon3;
use app\models\Weapon3;
use app\models\WeaponType3;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;

trait QueryDecoratorTrait
{
    public function decorateQuery(ActiveQuery $query): void
    {
        if ($this->hasErrors()) {
            Yii::warning('This form has errors', __METHOD__);
            $query->andWhere('1 <> 1'); // make no results
            return;
        }

        $this->decorateGroupFilter(
            $query,
            '{{%battle3}}.[[lobby_id]]',
            $this->lobby,
            Lobby3::class,
            LobbyGroup3::class,
            '{{%lobby3}}.[[group_id]]',
        );

        $this->decorateGroupFilter(
            $query,
            '{{%battle3}}.[[rule_id]]',
            $this->rule,
            Rule3::class,
            RuleGroup3::class,
            '{{%rule3}}.[[group_id]]',
        );

        $this->decorateSimpleFilter($query, '{{%battle3}}.[[map_id]]', $this->map, Map3::class);
        $this->decorateWeaponFilter($query, $this->weapon);
        $this->decorateResultFilter($query, $this->result);
        $this->decorateKnockoutFilter($query, $this->knockout);
    }

    private function decorateWeaponFilter(ActiveQuery $query, ?string $key): void
    {
        $key = \trim((string)$key);
        if ($key === '') {
            return;
        }

        switch (\substr($key, 0, 1)) {
            case Battle3FilterForm::PREFIX_WEAPON_TYPE:
                $this->decorateSimpleFilter(
                    $query->innerJoinWith(['weapon.mainweapon'], false),
                    '{{%mainweapon3}}.[[type_id]]',
                    \substr($key, 1),
                    WeaponType3::class,
                );
                return;

            case Battle3FilterForm::PREFIX_WEAPON_SUB:
                $this->decorateSimpleFilter(
                    $query->innerJoinWith(['weapon'], false),
                    '{{%weapon3}}.[[subweapon_id]]',
                    \substr($key, 1),
                    Subweapon3::class,
                );
                return;

            case Battle3FilterForm::PREFIX_WEAPON_SPECIAL:
                $this->decorateSimpleFilter(
                    $query->innerJoinWith(['weapon'], false),
                    '{{%weapon3}}.[[special_id]]',
                    \substr($key, 1),
                    Special3::class,
                );
                return;

            case Battle3FilterForm::PREFIX_WEAPON_MAIN:
                $this->decorateSimpleFilter(
                    $query->innerJoinWith(['weapon'], false),
                    '{{%weapon3}}.[[mainweapon_id]]',
                    \substr($key, 1),
                    Mainweapon3::class,
                );
                return;

            default:
                $this->decorateSimpleFilter(
                    $query,
                    '{{%battle3}}.[[weapon_id]]',
                    $key,
                    Weapon3::class,
                );
                return;
        }
    }

    private function decorateResultFilter(ActiveQuery $query, ?string $key): void
    {
        $key = \trim((string)$key);
        if ($key === '') {
            return;
        }

        switch ($key) {
            case Battle3FilterForm::RESULT_NOT_DRAW:
                $query->andWhere([
                    '{{%battle3}}.[[result_id]]' => ArrayHelper::getColumn(
                        Result3::find()
                            ->andWhere(['not', ['key' => 'draw']])
                            ->all(),
                        'id',
                    ),
                ]);
                return;

            case Battle3FilterForm::RESULT_NOT_WIN:
                $query->andWhere([
                    '{{%battle3}}.[[result_id]]' => ArrayHelper::getColumn(
                        Result3::find()
                            ->andWhere(['not', ['key' => 'win']])
                            ->all(),
                        'id',
                    ),
                ]);
                return;

            case Battle3FilterForm::RESULT_UNKNOWN:
                $query->andWhere(['{{%battle3}}.[[result_id]]' => null]);
                return;

            case Battle3FilterForm::RESULT_VIRTUAL_LOSE:
                $query->andWhere([
                    '{{%battle3}}.[[result_id]]' => self::findIdsByKey(
                        Result3::class,
                        ['lose', 'exempted_lose'],
                    ),
                ]);
                return;

            case Battle3FilterForm::RESULT_WIN_OR_LOSE:
                $query->andWhere([
                    '{{%battle3}}.[[result_id]]' => self::findIdsByKey(
                        Result3::class,
                        ['win', 'lose'],
                    ),
                ]);
                return;

            default:
                $this->decorateSimpleFilter(
                    $query,
                    '{{%battle3}}.[[result_id]]',
                    $key,
                    Result3::class,
                );
                return;
        }
    }

    private function decorateKnockoutFilter(ActiveQuery $query, ?string $key): void
    {
        $key = \trim((string)$key);
        if ($key === '') {
            return;
        }

        switch ($key) {
            case 'yes':
            case 'no':
                $query->andWhere(['{{%battle3}}.[[is_knockout]]' => $key === 'yes']);
                return;

            default:
                $query->andWhere('1 <> 1');
                return;
        }
    }

    /**
     * @phpstan-param class-string<ActiveRecord> $modelClass
     * @phpstan-param class-string<ActiveRecord> $groupClass
     */
    private function decorateGroupFilter(
        ActiveQuery $query,
        string $column,
        ?string $key,
        string $modelClass,
        string $groupClass,
        string $groupAttr // group_id
    ): void {
        $key = \trim((string)$key);
        if ($key !== '') {
            if (!\str_starts_with($key, '@')) {
                // NOT group
                $this->decorateSimpleFilter($query, $column, $key, $modelClass);
                return;
            }


            if (!$groupId = self::findIdByKey($groupClass, \substr($key, 1))) {
                $query->andWhere('1 <> 1');
                return;
            }

            $query->andWhere([
                $column => ArrayHelper::getColumn(
                    $modelClass::find()
                        ->andWhere([$groupAttr => $groupId])
                        ->all(),
                    'id',
                ),
            ]);
        }
    }

    /**
     * @phpstan-param class-string<ActiveRecord> $modelClass
     */
    private function decorateSimpleFilter(
        ActiveQuery $query,
        string $column,
        ?string $key,
        string $modelClass
    ): void {
        $key = \trim((string)$key);
        if ($key !== '') {
            $query->andWhere([
                $column => self::findIdByKey($modelClass, $key),
            ]);
        }
    }

    /**
     * @phpstan-param class-string<ActiveRecord> $modelClass
     */
    private static function findIdByKey(
        string $modelClass,
        string $key,
        string $column = 'key'
    ): ?int {
        $model = $modelClass::find()
            ->andWhere([$column => $key])
            ->limit(1)
            ->one();
        return $model ? (int)$model->id : null;
    }

    /**
     * @phpstan-param class-string<ActiveRecord> $modelClass
     * @param string[] $key
     * @return int[]
     */
    private static function findIdsByKey(
        string $modelClass,
        array $key,
        string $column = 'key'
    ): array {
        return \array_filter(
            ArrayHelper::getColumn(
                $modelClass::find()
                    ->andWhere([$column => $key])
                    ->all(),
                fn (ActiveRecord $model) => \filter_var($model->id, FILTER_VALIDATE_INT),
            ),
            fn ($value): bool => \is_int($value), // PHP 8: \is_int(...)
        );
    }
}

<?php
/**
 * @copyright Copyright (C) 2015-2017 AIZAWA Hina
 * @license https://github.com/fetus-hina/stat.ink/blob/master/LICENSE MIT
 * @author AIZAWA Hina <hina@bouhime.com>
 */

namespace app\models;

use Yii;
use app\components\helpers\Translator;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "brand2".
 *
 * @property integer $id
 * @property string $key
 * @property string $name
 * @property integer $strength_id
 * @property integer $weakness_id
 *
 * @property Ability2 $strength
 * @property Ability2 $weakness
 */
class Brand2 extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'brand2';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['key', 'name'], 'required'],
            [['strength_id', 'weakness_id'], 'default', 'value' => null],
            [['strength_id', 'weakness_id'], 'integer'],
            [['key', 'name'], 'string', 'max' => 32],
            [['key'], 'unique'],
            [['strength_id'], 'exist', 'skipOnError' => true,
                'targetClass' => Ability2::class,
                'targetAttribute' => ['strength_id' => 'id'],
            ],
            [['weakness_id'], 'exist', 'skipOnError' => true,
                'targetClass' => Ability2::class,
                'targetAttribute' => ['weakness_id' => 'id'],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'key' => 'Key',
            'name' => 'Name',
            'strength_id' => 'Strength ID',
            'weakness_id' => 'Weakness ID',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getStrength()
    {
        return $this->hasOne(Ability2::class, ['id' => 'strength_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getWeakness()
    {
        return $this->hasOne(Ability2::class, ['id' => 'weakness_id']);
    }

    public function toJsonArray()
    {
        return [
            'key' => $this->key,
            'name' => Translator::translateToAll('app-brand2', $this->name),
            'strength' => $this->strength ? $this->strength->toJsonArray() : null,
            'weakness' => $this->weakness ? $this->weakness->toJsonArray() : null,
        ];
    }
}

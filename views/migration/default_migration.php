<?php

declare(strict_types=1);

/**
 * This view is used by console/controllers/MigrateController.php
 * The following variables are available in this view:
 */

/**
 * @var string $className the new migration class name without namespace
 * @var string $namespace the new migration class namespace
 */
?>
<?= $this->renderFile(__DIR__ . '/migration.php', [
    'className' => $className,
    'namespace' => $namespace,
    'inTransaction' => true,
    'upCode' => 'return true;',
    'downCode' => implode("\n", [
        'echo "' . addslashes($className) . ' cannot be reverted.\n";',
        'return false;',
    ]),
]) ?>

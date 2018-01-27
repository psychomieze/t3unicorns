<?php
defined('TYPO3_MODE') or die('Access denied.');

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_adminpanel.php']['extendAdminPanel'][] = \Psychomieze\Unicorns\AdminPanel\DoctrineDebug::class;
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS'][\TYPO3\CMS\Core\Database\ConnectionPool::class]['getConnectionByName-postProcessNewConnection'][] = \Psychomieze\Unicorns\AdminPanel\DoctrineDebug::class;
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS'][\TYPO3\CMS\Core\Database\ConnectionPool::class]['getConnectionByName-postProcessExistingConnection'][] = \Psychomieze\Unicorns\AdminPanel\DoctrineDebug::class;
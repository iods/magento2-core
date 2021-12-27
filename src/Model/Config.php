<?php
/**
 * Core module for extending and testing functionality across Magento 2
 *
 * @package   Iods_Core
 * @author    Rye Miller <rye@drkstr.dev>
 * @copyright Copyright (c) 2021, Rye Miller (https://ryemiller.io)
 * @license   See LICENSE for license details.
 */
declare(strict_types=1);

namespace Iods\Core\Model;

/*
 * What are we doing here in this file?
 *
 * Managing common configuration settings used through Iods modules.
 */

use Iods\Core\Api\ConfigInterface;
use Magento\Config\Model\ResourceModel\Config as ConfigResource;
use Magento\Eav\Model\Entity;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class Config implements ConfigInterface
{
    /*
     * Do all modules get this?
     */
    const MODULE_NAME = 'Iods_Core';

    /*
     * What is important about this?
     */
    const SQL_UPDATE_LIMIT = 50000;

    protected array $_config = [
        'api' => ['path' => 'iods/env/iods_api_url'],
        'api_messaging' => ['path' => 'iods_core/env/iods_api_url_messaging'],
        'app_key' => ['path' => 'iods/settings/app_key'],
        'apiV1' => ['path' => 'iods/env/iods_api_v1_url'],
        'enable_debug' => ['path' => 'iods/settings/enable_debug'],
        'iods_active' => ['path' => 'iods/settings/active'],
        'secret' => ['path' => 'iods/settings/secret','encrypted' => true]
    ];

    protected ConfigResource $_configResource;

    protected EncryptorInterface $_encryptor;

    protected Entity $_entity;

    protected ModuleListInterface $_moduleList;

    protected ProductMetadataInterface $_productMetadata;

    protected ScopeConfigInterface $_scopeConfig;

    // array of int and string?
    protected array $_storeCode = [];

    protected StoreManagerInterface $_storeManager;

    protected WriterInterface $_writer;

    public function __construct(
        ConfigResource $configResource,
        EncryptorInterface $encryptor,
        Entity $entity,
        ModuleListInterface $moduleList,
        ProductMetadataInterface $productMetadata,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        WriterInterface $writer
    ) {
        $this->_configResource = $configResource; // what and why?
        $this->_encryptor = $encryptor; // what and why?
        $this->_entity = $entity;
        $this->_moduleList = $moduleList;
        $this->_productMetadata = $productMetadata;
        $this->_scopeConfig = $scopeConfig;
        $this->_storeManager = $storeManager;
        $this->_writer = $writer;
    }

    public function isEnabled(int $scopeId = null, string $scope = ScopeInterface::SCOPE_STORE): bool
    {
        return (bool) $this->getConfig('iods_core', $scopeId, $scope);
    }

    public function getConfig(string $key, int $scopeId = null, string $scope = ScopeInterface::SCOPE_STORE): ?string
    {
        $config = '';

        if (isset($this->_config[$key]['path'])) {
            $configPath = $this->_config[$key]['path'];
            if ($scopeId === null) {
                $scopeId = $this->_storeManager->getStore()->getId();
            }
            if (isset($this->_config[$key]['read_from_db'])) {
                $config = $this->getConfigFromDatabase($configPath, $scopeId, $scope);
            } else {
                $config = $this->_scopeConfig->getValue($configPath, $scopeId, $scope);
            }
            if (isset($this->_config[$key]['encrypted']) && $this->_config[$key]['encrypted'] === true && $config) {
                $config = $this->_encryptor->decrypt($config);
            }
        }

        return $config;
    }

    public function getConfigFromDatabase(string $path, int $id = null, string $scope = ScopeInterface::SCOPE_STORES): string
    {
        if ($scope == ScopeInterface::SCOPE_STORE) {
            $scope = ScopeInterface::SCOPE_STORES;
        }

        $conn = $this->_configResource->getConnection();
        if (!$conn) {
            return '';
        }

        $select = $conn->select()->from(
            $this->_configResource->getMainTable(),
            ['value']
        )->where(
            'path = ?',
            $path
        )->where(
            'scope = ?',
            $scope
        )->where(
            'scope_id = ?',
            $id
        );
        return $conn->fetchOne($select);
    }

    public function getConfigPath(string $key)
    {
        return $this->_config[$key]['path'];
    }

    public function getEavRowIdFieldName(): ?string
    {
        return $this->_entity->setType('catalog_product')->getLinkField();
    }

    public function getRowIdAvailability(): bool
    {
        return $this->getEavRowIdFieldName() == 'row_id';
    }

    public function getModuleVersion()
    {
        $module = $this->_moduleList->getOne(self::MODULE_NAME);
        return $module ? $module ['setup_version'] : null;
    }

    public function getUpdateSqlLimit(): int
    {
        return self::SQL_UPDATE_LIMIT;
    }

    public function delete(string $key, int $scopeId = null, string $scope = ScopeInterface::SCOPE_STORE): void
    {
        $configPath = $this->_config[$key]['path'];
        $scope = $scope ?: ScopeInterface::SCOPE_STORES;
        $scopeId = $scopeId === null ? $this->_storeManager->getStore()->getId() : $scopeId;

        $this->_writer->delete($configPath, $scope, $scopeId);
    }

    public function save(ConfigValue $configValue)
    {
        $this->_writer->save(
            $configValue->getPath(),
            $configValue->getValue(),
            $configValue->getScope()->getScopeCode(),
            $configValue->getScope()->getScopeCode()
        );
    }

//    public function save(string $key, string $value, int $scopeId = null, string $scope = ScopeInterface::SCOPE_STORE): void
//    {
//        $configPath = $this->_config[$key]['path'];
//        $scope = $scope ?: ScopeInterface::SCOPE_STORES;
//        $scopeId = $scopeId === null ? $this->_storeManager->getStore()->getId() : $scopeId;
//        if (isset($this->_config[$key]['encrypted']) && $this->_config[$key]['encrypted'] == true && $value) {
//            $value = $this->_encryptor->encrypt($value);
//        }
//        $this->_writer->save($configPath, $value, $scope, $scopeId);
//    }
}
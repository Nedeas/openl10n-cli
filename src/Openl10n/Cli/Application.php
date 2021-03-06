<?php

namespace Openl10n\Cli;

use Openl10n\Cli\ServiceContainer\Configuration\ConfigurationLoader;
use Openl10n\Cli\ServiceContainer\Exception\ConfigurationLoadingException;
use Openl10n\Cli\ServiceContainer\ExtensionManager;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class Application extends BaseApplication
{
    /**
     * @var ConfigurationLoader
     */
    protected $configurationLoader;

    /**
     * @var ExtensionManager
     */
    protected $extensionManager;

    /**
     * @var ContainerBuilder
     */
    protected $container;

    /**
     * @var boolean
     */
    protected $ignoreMissingConfiguration;

    /**
     * @param string              $name                Application name
     * @param string              $version             Application version
     * @param ConfigurationLoader $configurationLoader Configuration loader
     * @param ExtensionManager    $extensionManager    Extension manager
     */
    public function __construct($name, $version, ConfigurationLoader $configurationLoader, ExtensionManager $extensionManager)
    {
        parent::__construct($name, $version);

        $this->configurationLoader = $configurationLoader;
        $this->extensionManager = $extensionManager;

        $this->ignoreMissingConfiguration = false;
    }

    /**
     * @return ContainerInterface The container
     */
    public function getContainer()
    {
        if (null === $this->container) {
            $this->container = $this->createContainer();
        }

        return $this->container;
    }

    /**
     * Force container to be recreated.
     */
    public function destroyContainer()
    {
        $this->container = null;
    }

    /**
     * Ignore when the configuration file is missing.
     *
     * Useful for commands which don't need the configurated services
     * such as the `init` command.
     *
     * @param boolean $value
     */
    public function ignoreMissingConfiguration($value = true)
    {
        $this->ignoreMissingConfiguration = (bool) $value;
    }

    /**
     * Create a new container.
     *
     * @return ContainerInterface
     */
    protected function createContainer()
    {
        $container = new ContainerBuilder();

        // Default configuration.
        $container->set('application', $this);
        $container->set('configuration.loader', $this->configurationLoader);
        $container->set('extension_manager', $this->extensionManager);

        // Initialize extensions.
        $this->extensionManager->initialize($container);

        // Load configuration.
        try {
            $rawConfigs = $this->configurationLoader->loadConfiguration();

            $this->extensionManager->load($rawConfigs, $container);
        } catch (ConfigurationLoadingException $e) {
            // No configuration file found.
            // Continue with restricted services if `ignoreMissingConfiguration`
            // has been specified.
            if (!$this->ignoreMissingConfiguration) {
                throw $e;
            }
        }

        // Process compiler pass & compile container
        $container->compile();

        return $container;
    }
}

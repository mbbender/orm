<?php

namespace LaravelDoctrine\ORM;

use Doctrine\Common\Persistence\AbstractManagerRegistry;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\Persistence\Proxy;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMException;
use Illuminate\Contracts\Container\Container;

/**
 * Class IlluminateRegistry
 * @package LaravelDoctrine\ORM
 */
class IlluminateRegistry implements ManagerRegistry
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var array
     */
    protected $connections;

    /**
     * @var array
     */
    protected $managers;

    /**
     * @var string
     */
    protected $defaultConnection;

    /**
     * @var string
     */
    protected $defaultManager;

    /**
     * @var string
     */
    protected $proxyInterfaceName;


    public function __construct()
    {
        $this->name = 'IlluminateRegistry';
        $this->connections = [];
        $this->managers = [];
        $this->defaultManager = 'default';
        $this->defaultConnection = 'default';
        $this->proxyInterfaceName = Proxy::class;
    }

    /**
     * @param EntityManager $entityManager
     */
    public function registerManager($name, EntityManager $entityManager)
    {
        $this->managers[$name] = $entityManager;
    }

    /**
     * @return string
     */
    public function getDefaultConnection()
    {
        return $this->defaultConnection;
    }

    /**
     * @param string $defaultConnection
     */
    public function setDefaultConnection($defaultConnection)
    {
        $this->defaultConnection = $defaultConnection;
    }

    /**
     * @return string
     */
    public function getProxyInterfaceName()
    {
        return $this->proxyInterfaceName;
    }

    /**
     * @param string $proxyInterfaceName
     */
    public function setProxyInterfaceName($proxyInterfaceName)
    {
        $this->proxyInterfaceName = $proxyInterfaceName;
    }

    /**
     * Gets the name of the registry.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function getConnection($name = null)
    {
        if (null === $name) {
            $name = $this->defaultConnection;
        }

        if (!isset($this->connections[$name])) {
            throw new \InvalidArgumentException(sprintf('Doctrine %s Connection named "%s" does not exist.', $this->name, $name));
        }

        return $this->getService($this->connections[$name]);
    }

    /**
     * {@inheritdoc}
     */
    public function getConnectionNames()
    {
        return $this->connections;
    }

    /**
     * {@inheritdoc}
     */
    public function getConnections()
    {
        $connections = array();
        foreach ($this->connections as $name => $id) {
            $connections[$name] = $this->getService($id);
        }

        return $connections;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultConnectionName()
    {
        return $this->defaultConnection;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultManagerName()
    {
        return $this->defaultManager;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \InvalidArgumentException
     */
    public function getManager($name = null)
    {
        if (null === $name) {
            return $this->getDefaultManager();
        }

        if (!isset($this->managers[$name])) {
            throw new \InvalidArgumentException(sprintf('Doctrine %s Manager named "%s" does not exist.', $this->name, $name));
        }

        return $this->getService($this->managers[$name]);
    }

    public function getDefaultManager()
    {
        if(empty($this->managers))
            throw new \InvalidArgumentException(sprintf('No Doctrine Managers have been registered.'));

        if(isset($this->defaultManager) && isset($this->managers[$this->getDefaultManagerName()]))
            return $this->getManager($this->getDefaultManagerName());

        return $this->getService(head($this->managers));
    }

    /**
     * {@inheritdoc}
     */
    public function getManagerForClass($class)
    {
        // Check for namespace alias
        if (strpos($class, ':') !== false) {
            list($namespaceAlias, $simpleClassName) = explode(':', $class, 2);
            $class = $this->getAliasNamespace($namespaceAlias) . '\\' . $simpleClassName;
        }

        $proxyClass = new \ReflectionClass($class);
        if ($proxyClass->implementsInterface($this->proxyInterfaceName)) {
            $class = $proxyClass->getParentClass()->getName();
        }

        foreach ($this->managers as $id) {
            $manager = $this->getService($id);

            if (!$manager->getMetadataFactory()->isTransient($class)) {
                return $manager;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getManagerNames()
    {
        return $this->managers;
    }

    /**
     * {@inheritdoc}
     */
    public function getManagers()
    {
        $dms = array();
        foreach ($this->managers as $name => $id) {
            $dms[$name] = $this->getService($id);
        }

        return $dms;
    }

    /**
     * {@inheritdoc}
     */
    public function getRepository($persistentObjectName, $persistentManagerName = null)
    {
        return $this->getManager($persistentManagerName)->getRepository($persistentObjectName);
    }

    /**
     * {@inheritdoc}
     */
    public function resetManager($name = null)
    {
        if (null === $name) {
            $name = $this->defaultManager;
        }

        if (!isset($this->managers[$name])) {
            throw new \InvalidArgumentException(sprintf('Doctrine %s Manager named "%s" does not exist.', $this->name, $name));
        }

        // force the creation of a new document manager
        // if the current one is closed
        $this->resetService($this->managers[$name]);
    }


    /**
     * Fetches/creates the given services.
     * A service in this context is connection or a manager instance.
     *
     * @param string $name The name of the service.
     *
     * @return object The instance of the given service.
     */
    protected function getService($name)
    {
        return $this->container->make(self::getManagerNamePrefix() . $name);
    }


    /**
     * Resets the given services.
     * A service in this context is connection or a manager instance.
     *
     * @param string $name The name of the service.
     *
     * @return void
     */
    protected function resetService($name)
    {
        $this->container->forgetInstance(self::getManagerNamePrefix() . $name);
    }

    /**
     * Resolves a registered namespace alias to the full namespace.
     * This method looks for the alias in all registered object managers.
     *
     * @param string $alias The alias.
     *
     * @throws ORMException
     * @return string       The full namespace.
     */
    public function getAliasNamespace($alias)
    {
        foreach (array_keys($this->getManagers()) as $name) {
            try {
                return $this->getManager($name)->getConfiguration()->getEntityNamespace($alias);
            } catch (ORMException $e) {
            }
        }
        throw ORMException::unknownEntityNamespace($alias);
    }


    /**
     * @return string
     */
    public static function getManagerNamePrefix()
    {
        return 'doctrine.manager.';
    }

    /**
     * @return string
     */
    public static function getConnectionNamePrefix()
    {
        return 'doctrine.connection.';
    }
}

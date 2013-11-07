<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license.
 */

namespace ZfcRbac\Role;

use Zend\Cache\Storage\StorageInterface as CacheInterface;
use Zend\EventManager\AbstractListenerAggregate;
use Zend\EventManager\EventManagerInterface;
use Zend\Permissions\Rbac\RoleInterface;
use ZfcRbac\Service\RbacEvent;

/**
 * Simple listener that is used to load roles
 */
class RoleLoaderListener extends AbstractListenerAggregate
{
    /**
     * @var RoleProviderInterface
     */
    protected $roleProvider;

    /**
     * @var CacheInterface|null
     */
    protected $cache;

    /**
     * @var string
     */
    protected $cacheKey = 'zfc_rbac_roles';

    /**
     * Constructor
     *
     * @param RoleProviderInterface $roleProvider
     * @param CacheInterface|null   $cache
     */
    public function __construct(RoleProviderInterface $roleProvider, CacheInterface $cache = null)
    {
        $this->roleProvider = $roleProvider;
        $this->cache        = $cache;
    }

    /**
     * Get the cache storage
     *
     * @return CacheInterface|null
     */
    public function getCache()
    {
        return $this->cache;
    }

    /**
     * Set the cache key
     *
     * @param  string $cacheKey
     * @return void
     */
    public function setCacheKey($cacheKey)
    {
        $this->cacheKey = (string) $cacheKey;
    }

    /**
     * Get the cache key
     *
     * @return string
     */
    public function getCacheKey()
    {
        return $this->cacheKey;
    }

    /**
     * {@inheritDoc}
     */
    public function attach(EventManagerInterface $events)
    {
        $sharedEventManager = $events->getSharedManager();

        $sharedEventManager->attach(
            'ZfcRbac\Service\AuthorizationService',
            RbacEvent::EVENT_LOAD_ROLES,
            array($this, 'onLoadRoles')
        );
    }

    /**
     * Inject the loaded roles inside the Rbac container
     *
     * @param  RbacEvent $event
     * @return void
     */
    public function onLoadRoles(RbacEvent $event)
    {
        $rbac  = $event->getRbac();
        $roles = $this->getRoles($event);

        if (!is_array($roles)) {
            $roles = array($roles);
        }

        foreach ($roles as $key => $value) {
            if ($value instanceof RoleInterface) {
                $rbac->addRole($value);
            } elseif (is_int($key)) {
                $rbac->addRole($value);
            } else {
                $rbac->addRole($key, $value);
            }
        }
    }

    /**
     * Get the roles, optionally fetched from cache
     *
     * @param  RbacEvent $event
     * @return mixed
     */
    protected function getRoles(RbacEvent $event)
    {
        if (null === $this->cache) {
            return $this->roleProvider->getRoles($event);
        }

        if ($this->cache->hasItem($this->cacheKey)) {
            return $this->cache->getItem($this->cacheKey);
        }

        $result = $this->roleProvider->getRoles($event);
        $this->cache->setItem($this->cacheKey, $result);

        return $result;
    }
}
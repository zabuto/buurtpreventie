<?php

namespace Zabuto\Bundle\UserBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class GroupRoleType extends AbstractType
{

    /**
     * @var array
     */
    protected $roles = array();

    /**
     * Constructor
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->roles = $this->_getSecurityRoles($container);
    }

    /**
     * Set default options
     *
     * @param OptionsResolverInterface $resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'choices' => $this->roles
        ));
    }

    /**
     * Get parent
     *
     * @return null|string|\Symfony\Component\Form\FormTypeInterface
     */
    public function getParent()
    {
        return 'choice';
    }

    /**
     * Set name
     *
     * @return string
     */
    public function getName()
    {
        return 'zabuto_user_group_role';
    }

    /**
     * Get available roles from security
     *
     * @param ContainerInterface $container
     * @return array
     */
    private function _getSecurityRoles(ContainerInterface $container)
    {
        $securityRoles = $container->getParameter('security.role_hierarchy.roles');
        $roles = array();
        foreach ($securityRoles as $role => $hierarchy) {
            if (substr($role, 0, 5) === 'ROLE_') {
                $roles[$role] = ucfirst(strtolower(substr($role, 5)));
            }
        }
        return $roles;
    }

}

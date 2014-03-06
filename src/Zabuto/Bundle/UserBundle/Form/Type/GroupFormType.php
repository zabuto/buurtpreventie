<?php

namespace Zabuto\Bundle\UserBundle\Form\Type;

use FOS\UserBundle\Form\Type\GroupFormType as BaseType;
use Symfony\Component\Form\FormBuilderInterface;

class GroupFormType extends BaseType
{

    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('name', null, array('label' => 'form.group_name', 'translation_domain' => 'FOSUserBundle'));
        $builder->add('roles', 'zabuto_user_group_role', array('label' => 'form.group_roles', 'translation_domain' => 'FOSUserBundle', 'multiple' => true));
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'zabuto_user_group';
    }

}

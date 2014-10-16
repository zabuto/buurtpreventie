<?php

namespace Zabuto\Bundle\UserBundle\Form\Type;

use Symfony\Component\Form\FormBuilderInterface;
use FOS\UserBundle\Form\Type\RegistrationFormType as BaseType;

class UserEditFormType extends BaseType
{

    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('realname', null, array(
            'label' => 'form.realname',
            'translation_domain' => 'FOSUserBundle',
        ));

        $builder->add('email', 'email', array(
            'label' => 'form.email',
            'translation_domain' => 'FOSUserBundle',
        ));

        $builder->add('address', null, array(
            'label' => 'form.address',
            'translation_domain' => 'FOSUserBundle',
        ));

        $builder->add('phone', null, array(
            'label' => 'form.phone',
            'translation_domain' => 'FOSUserBundle',
        ));

        $builder->add('credit', 'choice', array(
            'label' => 'form.credit',
            'translation_domain' => 'FOSUserBundle',
            'choices' => array('1' => 'Ja', '0' => 'Nee')
        ));

        $builder->add('groups', 'entity', array(
            'class' => 'ZabutoUserBundle:Group',
            'property' => 'name',
            'label' => 'form.usergroup',
            'translation_domain' => 'FOSUserBundle',
            'expanded' => false,
            'multiple' => true,
            'required' => true,
        ));

        $builder->add('enabled', 'choice', array(
            'label' => 'form.enabled',
            'translation_domain' => 'FOSUserBundle',
            'choices' => array('1' => 'Ja', '0' => 'Nee')
        ));
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'zabuto_user_edit';
    }

}

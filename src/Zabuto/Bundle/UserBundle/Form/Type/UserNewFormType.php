<?php

namespace Zabuto\Bundle\UserBundle\Form\Type;

use Symfony\Component\Form\FormBuilderInterface;
use FOS\UserBundle\Form\Type\RegistrationFormType as BaseType;

class UserNewFormType extends BaseType
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

        $builder->add('groups', 'entity', array(
            'class' => 'ZabutoUserBundle:Group',
            'property' => 'name',
            'label' => 'form.usergroup',
            'translation_domain' => 'FOSUserBundle',
            'multiple' => false,
            'empty_value' => '(kies de gebruikersgroep)',
        ));

        $builder->add('plainPassword', 'repeated', array(
            'type' => 'password',
            'options' => array(
                'translation_domain' => 'FOSUserBundle',
            ),
            'first_options' => array('label' => 'form.password'),
            'second_options' => array('label' => 'form.password_confirmation'),
            'invalid_message' => 'fos_user.password.mismatch',
        ));

        $builder->add('save', 'submit', array(
            'label' => 'form.button.new',
            'translation_domain' => 'FOSUserBundle',
            'attr' => array(
                'class' => 'btn btn-primary',
                'data-first-button',
            ),
        ));

        $builder->add('cancel', 'button', array(
            'label' => 'form.button.cancel',
            'translation_domain' => 'FOSUserBundle',
            'attr' => array(
                'class' => 'btn btn-default',
                'data-last-button',
            ),
        ));
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'zabuto_user_new';
    }

}

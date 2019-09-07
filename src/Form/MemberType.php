<?php

namespace App\Form;

use App\Entity\User;
use Misd\PhoneNumberBundle\Form\Type\PhoneNumberType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * MemberType
 */
class MemberType extends AbstractType
{
    /**
     * @param  FormBuilderInterface $builder
     * @param  array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', null, ['label' => 'user.name'])
            ->add('address', null, ['label' => 'user.address'])
            ->add('phone', PhoneNumberType::class, [
                'required' => false,
                'label'    => 'user.phone',
            ])
            ->add('mobile', PhoneNumberType::class, [
                'required' => false,
                'label'    => 'user.mobile',
            ])
            ->add('email', null, [
                'label' => 'user.email',
                'help'  => 'user.email-readonly',
                'attr'  => [
                    'readonly' => true,
                ],
            ])
            ->add('credited', null, ['label' => 'user.credited']);
    }

    /**
     * @param  OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}

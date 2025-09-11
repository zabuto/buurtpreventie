<?php declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use libphonenumber\PhoneNumberFormat;
use Misd\PhoneNumberBundle\Form\Type\PhoneNumberType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MemberType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', null, ['label' => 'user.name'])
            ->add('address', null, ['label' => 'user.address'])
            ->add('phone', PhoneNumberType::class, [
                'required' => false,
                'label' => 'user.phone',
                'format' => PhoneNumberFormat::NATIONAL,
                'number_type' => PhoneNumberType::NUMBER_TYPE_TEL,
            ])
            ->add('mobile', PhoneNumberType::class, [
                'required' => false,
                'label' => 'user.mobile',
                'format' => PhoneNumberFormat::NATIONAL,
                'number_type' => PhoneNumberType::NUMBER_TYPE_TEL,
            ])
            ->add('email', null, [
                'label' => 'user.email',
                'help' => 'user.email-readonly',
                'attr' => [
                    'readonly' => true,
                ],
            ])
            ->add('credited', ChoiceType::class, [
                'label' => 'user.credited',
                'choices' => [
                    'No' => 0,
                    'Yes' => 1,
                ],
            ])
            ->add('permitted', ChoiceType::class, [
                'label' => 'user.permitted',
                'help' => 'user.permitted.help',
                'choices' => [
                    'No' => 0,
                    'Yes' => 1,
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}

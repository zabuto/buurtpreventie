<?php declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class PasswordChangeType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    )
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('current', PasswordType::class, [
                'label' => 'security.password-current',
                'required' => true,
            ])
            ->add('new', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => $this->translator->trans('security.password-match'),
                'options' => ['attr' => ['class' => 'password-field']],
                'required' => true,
                'first_options' => ['label' => 'security.password-new'],
                'second_options' => ['label' => 'security.password-new-repeat'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_field_name' => '_change_password',
        ]);
    }
}

<?php declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserAddType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', null, ['label' => 'user.name'])
            ->add('email', null, ['label' => 'user.email'])
            ->add('roles', ChoiceType::class, [
                'label' => 'user.roles',
                'multiple' => true,
                'choices' => [
                    'security.role.walker' => 'ROLE_WALK',
                    'security.role.coordinator' => 'ROLE_COORDINATE',
                    'security.role.analyst' => 'ROLE_ANALYST',
                    'security.role.admin' => 'ROLE_ADMIN',
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

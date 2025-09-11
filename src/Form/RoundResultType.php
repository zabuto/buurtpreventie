<?php declare(strict_types=1);

namespace App\Form;

use App\Entity\Result;
use App\Entity\RoundResult;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RoundResultType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('result', EntityType::class, [
                'class' => Result::class,
                'label' => 'walk.result',
            ])
            ->add('memo', TextareaType::class, [
                'label' => 'walk.result.memo',
                'attr' => [
                    'rows' => 4,
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RoundResult::class,
        ]);
    }
}

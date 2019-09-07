<?php

namespace App\Form;

use App\Entity\Result;
use App\Entity\RoundResult;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * RoundResultType
 */
class RoundResultType extends AbstractType
{
    /**
     * @param  FormBuilderInterface $builder
     * @param  array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('result', EntityType::class, [
                'class' => Result::class,
                'label' => 'walk.result',
            ])
            ->add('memo', TextareaType::class, [
                'label' => 'walk.result.memo',
                'attr'  => [
                    'rows' => 4,
                ],
            ]);
    }

    /**
     * @param  OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => RoundResult::class,
        ]);
    }
}

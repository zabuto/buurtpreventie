<?php

namespace App\Form;

use App\Entity\MeetingPoint;
use App\Entity\Round;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * RoundType
 */
class RoundType extends AbstractType
{
    /**
     * @param  FormBuilderInterface $builder
     * @param  array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $dateOptions = ['widget' => 'single_text', 'label' => 'walk.round-date'];
        if (false === $options['date_show']) {
            $dateOptions['attr'] = ['style' => 'display:none;'];
            $dateOptions['label_attr'] = ['style' => 'display:none;'];
        }

        $builder
            ->add('date', DateType::class, $dateOptions)
            ->add('time', TimeType::class, [
                'widget'       => 'single_text',
                'with_seconds' => false,
                'label'        => 'walk.round-time',
            ])
            ->add('meetingPoint', EntityType::class, [
                'class' => MeetingPoint::class,
                'label' => 'walk.meeting-point',
            ])
            ->add('memo', TextareaType::class, [
                'mapped' => false,
                'label'  => 'comment.memo',
            ]);
    }

    /**
     * @param  OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Round::class,
            'date_show'  => false,
        ]);
    }
}

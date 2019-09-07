<?php

namespace App\Form;

use App\Entity\MeetingPoint;
use App\Entity\Round;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * RoundMeetingPointType
 */
class RoundMeetingPointType extends AbstractType
{
    /**
     * @param  FormBuilderInterface $builder
     * @param  array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('meetingPoint', EntityType::class, [
                'class' => MeetingPoint::class,
                'label' => 'walk.meeting-point',
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

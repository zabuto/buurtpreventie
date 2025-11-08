<?php declare(strict_types=1);

namespace App\Form;

use App\Entity\MeetingPoint;
use App\Entity\Round;
use DateTimeImmutable;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RoundType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $dateOptions = ['mapped' => false, 'required' => true, 'widget' => 'single_text', 'label' => 'walk.round-date'];
        if (false === $options['date_show']) {
            $dateOptions['attr'] = ['style' => 'display:none;'];
            $dateOptions['label_attr'] = ['style' => 'display:none;'];
        }

        $builder
            ->add('date', DateType::class, $dateOptions)
            ->add('time', TimeType::class, [
                'mapped' => false,
                'required' => true,
                'widget' => 'single_text',
                'with_seconds' => false,
                'label' => 'walk.round-time',
            ])
            ->add('meetingPoint', EntityType::class, [
                'class' => MeetingPoint::class,
                'label' => 'walk.meeting-point',
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('mp')
                        ->orderBy('mp.description', 'ASC');
                },
            ])
            ->add('memo', TextareaType::class, [
                'mapped' => false,
                'label' => 'comment.memo',
            ]);

        $builder->addEventListener(FormEvents::POST_SET_DATA, static function (FormEvent $event): void {
            /** @var Round|null $round */
            $round = $event->getData();
            $form = $event->getForm();

            if (null !== $round && null !== $round->getDatetime()) {
                $form->get('date')->setData(clone $round->getDatetime());
                $form->get('time')->setData(null);
            }
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            /** @var Round $round */
            $round = $event->getData();
            $form = $event->getForm();

            $string = sprintf('%s %s', $form->get('date')->getData(), $form->get('time')->getData());
            $datetime = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $string);
            $round->setDatetime($datetime);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Round::class,
            'date_show' => false,
        ]);
    }
}

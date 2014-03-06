<?php

namespace Zabuto\Bundle\BuurtpreventieBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class LooptoelichtingFormType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('memo', 'textarea', array(
            'label' => false,
            'required' => true,
            'attr' => array('rows' => '3'),
            'constraints' => array(
                new NotBlank(array('message' => 'Vul een toelichting in')),
            ),
        ));
    }

    /**
     * @param OptionsResolverInterface $resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'Zabuto\Bundle\BuurtpreventieBundle\Entity\Looptoelichting',
        ));
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'zabuto_buurtpreventie_form_type_looptoelichting';
    }
}

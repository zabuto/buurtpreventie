<?php

namespace Zabuto\Bundle\BuurtpreventieBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class LoopschemaResultaatFormType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('resultaat', null, array(
                'label' => false,
                'required' => true,
                'empty_value' => 'Maak een keuze',
                'constraints' => array(
                    new NotBlank(array('message' => 'Vul het resultaat in')),
                ),
            )
        );
        $builder->add('bijzonderheden', 'textarea', array(
                'label' => 'Geef een toelichting bij bijzonderheden',
                'required' => false,
                'attr' => array('rows' => '3'),
            )
        );
    }

    /**
     * @param OptionsResolverInterface $resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'Zabuto\Bundle\BuurtpreventieBundle\Entity\Loopschema'
        ));
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'zabuto_buurtpreventie_form_type_loopschema_resultaat';
    }
}

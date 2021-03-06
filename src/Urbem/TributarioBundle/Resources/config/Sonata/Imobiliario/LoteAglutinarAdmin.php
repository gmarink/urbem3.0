<?php

namespace Urbem\TributarioBundle\Resources\config\Sonata\Imobiliario;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\Request;
use Urbem\CoreBundle\Entity\Administracao\Cadastro;
use Urbem\CoreBundle\Entity\Administracao\Modulo;
use Urbem\CoreBundle\Entity\Imobiliario\Lote;
use Urbem\CoreBundle\Entity\Imobiliario\PontoCardeal;
use Urbem\CoreBundle\Entity\Imobiliario\Trecho;
use Urbem\CoreBundle\Entity\SwAssunto;
use Urbem\CoreBundle\Entity\SwClassificacao;
use Urbem\CoreBundle\Entity\SwMunicipio;
use Urbem\CoreBundle\Entity\SwProcesso;
use Urbem\CoreBundle\Entity\SwUf;
use Urbem\CoreBundle\Helper\DateTimeMicrosecondPK;
use Urbem\CoreBundle\Model\Administracao\ConfiguracaoModel;
use Urbem\CoreBundle\Model\Imobiliario\LoteModel;
use Urbem\CoreBundle\Resources\config\Sonata\AbstractSonataAdmin as AbstractAdmin;
use Sonata\AdminBundle\Form\FormMapper;

class LoteAglutinarAdmin extends AbstractAdmin
{
    protected $baseRouteName = 'urbem_tributario_imobiliario_lote_aglutinar';
    protected $baseRoutePattern = 'tributario/cadastro-imobiliario/lote-aglutinar';
    protected $legendButtonSave = array('icon' => 'fullscreen_exit', 'text' => 'Aglutinar');
    protected $includeJs = array(
        '/administrativo/javascripts/administracao/atributo-dinamico-component.js',
        '/core/javascripts/sw-processo.js',
        '/tributario/javascripts/imobiliario/lote-confrontacoes.js',
        '/tributario/javascripts/imobiliario/lote-aglutinar.js'
    );

    /**
     * @return array
     */
    public function getPersistentParameters()
    {
        if (!$this->getRequest()) {
            return array();
        }

        return array(
            'codLote' => $this->getRequest()->get('codLote'),
        );
    }

    /**
     * @return null|Lote
     */
    public function getLote()
    {
        /** @var EntityManager $em */
        $em = $this->modelManager->getEntityManager($this->getClass());

        $lote = null;
        if ($this->getPersistentParameter('codLote')) {
            /** @var Lote $lote */
            $lote = $em->getRepository(Lote::class)->find($this->getPersistentParameter('codLote'));
        }
        return $lote;
    }

    /**
     * @param FormMapper $formMapper
     */
    protected function configureFormFields(FormMapper $formMapper)
    {
        $id = $this->getAdminRequestId();

        $this->setBreadCrumb($id ? ['id' => $id] : []);

        /** @var EntityManager $em */
        $em = $this->modelManager->getEntityManager($this->getClass());

        $configuracaoModel = new ConfiguracaoModel($em);
        $codUf = $configuracaoModel->pegaConfiguracao('cod_uf', Modulo::MODULO_ADMINISTRATIVO, $this->getExercicio(), true);
        $codMunicipio = $configuracaoModel->pegaConfiguracao('cod_municipio', Modulo::MODULO_ADMINISTRATIVO, $this->getExercicio(), true);

        $uf = $municipio = null;
        if (((int) $codUf) && ((int) $codMunicipio)) {
            $uf = $em->getRepository(SwUf::class)->find((integer) $codUf);
            $municipio = $em->getRepository(SwMunicipio::class)->findOneBy(array('codMunicipio' => (integer) $codMunicipio, 'codUf' => (integer) $codUf));
        }

        $lote = $this->getLote();

        $fieldOptions = array();

        $fieldOptions['codLote'] = array(
            'mapped' => false
        );

        $fieldOptions['codCadastro'] = array(
            'mapped' => false
        );

        $fieldOptions['codProprietario'] = array(
            'mapped' => false
        );

        $fieldOptions['dadosLote'] = array(
            'label' => false,
            'mapped' => false,
            'template' => 'TributarioBundle::Imobiliario/Lote/dadosAglutinar.html.twig',
            'data' => array(
                'lote' => $lote
            )
        );

        $fieldOptions['codClassificacao'] = array(
            'label' => 'label.imobiliarioLote.classificacao',
            'class' => SwClassificacao::class,
            'placeholder' => 'label.selecione',
            'mapped' => false,
            'required' => false,
            'query_builder' => function (EntityRepository $er) {
                return $er->createQueryBuilder('o')
                    ->orderBy('o.codClassificacao', 'ASC');
            },
            'attr' => array(
                'class' => 'select2-parameters '
            )
        );

        $fieldOptions['codAssunto'] = array(
            'label' => 'label.imobiliarioLote.assunto',
            'placeholder' => 'label.selecione',
            'choices' => array(),
            'mapped' => false,
            'required' => false,
            'attr' => array(
                'class' => 'select2-parameters '
            )
        );

        $fieldOptions['codProcesso'] = array(
            'label' => 'label.imobiliarioLote.processo',
            'placeholder' => 'label.selecione',
            'choices' => array(),
            'mapped' => false,
            'required' => false,
            'attr' => array(
                'class' => 'select2-parameters '
            )
        );

        $fieldOptions['fkImobiliarioPontoCardeal'] = array(
            'label' => 'label.imobiliarioLote.pontoCardeal',
            'class' => PontoCardeal::class,
            'mapped' => false,
            'required' => true,
            'attr' => array(
                'class' => 'select2-parameters '
            )
        );

        $choices = array(
            'trecho' => $this->getTranslator()->trans('label.imobiliarioLote.trecho'),
            'lote' => $this->getTranslator()->trans('label.imobiliarioLote.lote'),
            'outros' => $this->getTranslator()->trans('label.imobiliarioLote.outros'),
        );

        $fieldOptions['confrontacaoTipo'] = array(
            'label' => 'label.imobiliarioLote.tipo',
            'mapped' => false,
            'expanded' => true,
            'choices' => array_flip($choices),
            'data' => 'trecho',
            'label_attr' => array(
                'class' => 'checkbox-sonata'
            ),
            'attr' => array(
                'class' => 'checkbox-sonata'
            )
        );

        $fieldOptions['extensao'] = array(
            'label' => 'label.imobiliarioLote.extensao',
            'mapped' => false,
            'required' => true,
            'attr' => array(
                'class' => 'money '
            )
        );

        $fieldOptions['principal'] = array(
            'label' => 'label.imobiliarioLote.testada',
            'mapped' => false,
            'expanded' => true,
            'required' => true,
            'choices' => array(
                'label_type_yes' => 1,
                'label_type_no' => 0
            ),
            'data' => 0,
            'label_attr' => array(
                'class' => 'checkbox-sonata'
            ),
            'attr' => array(
                'class' => 'checkbox-sonata'
            )
        );

        $fieldOptions['fkSwUf'] = array(
            'label' => 'label.imobiliarioFaceQuadra.estado',
            'class' => SwUf::class,
            'choice_label' => 'nomUf',
            'choice_value' => 'codUf',
            'placeholder' => 'label.selecione',
            'mapped' => false,
            'required' => true,
            'query_builder' => function (EntityRepository $er) {
                return $er->createQueryBuilder('uf')
                    ->where('uf.codUf != :codUf')
                    ->setParameter('codUf', LoteAdmin::NAO_INFORMADO);
            },
            'data' => $uf,
            'attr' => array(
                'class' => 'select2-parameters '
            )
        );

        $fieldOptions['fkSwMunicipio'] = array(
            'label' => 'label.imobiliarioFaceQuadra.municipio',
            'class' => SwMunicipio::class,
            'choice_label' => 'nomMunicipio',
            'choice_value' => 'codMunicipio',
            'placeholder' => 'label.selecione',
            'mapped' => false,
            'required' => true,
            'query_builder' => function (EntityRepository $er) {
                return $er->createQueryBuilder('m')
                    ->where('m.codMunicipio != :codMunicipio')
                    ->setParameter('codMunicipio', LoteAdmin::NAO_INFORMADO);
            },
            'data' => $municipio,
            'attr' => array(
                'class' => 'select2-parameters '
            )
        );

        $fieldOptions['fkImobiliarioTrecho'] = array(
            'label' => 'label.imobiliarioFaceQuadra.trecho',
            'class' => Trecho::class,
            'req_params' => [
                'codUf' => 'varJsCodUf',
                'codMunicipio' => 'varJsCodMunicipio'
            ],
            'json_from_admin_code' => $this->code,
            'json_query_builder' => function (EntityRepository $er, $term, Request $request) {
                $qb = $er->createQueryBuilder('o');
                $qb->innerJoin('o.fkSwLogradouro', 'l');
                $qb->innerJoin('l.fkSwNomeLogradouros', 'nl');
                $qb->innerJoin('nl.fkSwTipoLogradouro', 'tl');
                $qb->where('l.codUf = :codUf');
                $qb->andWhere('l.codMunicipio = :codMunicipio');
                $qb->andWhere($qb->expr()->orX(
                    $qb->expr()->eq('o.codTrecho', ':codTrecho'),
                    $qb->expr()->like("LOWER(CONCAT(tl.nomTipo, ' ', nl.nomLogradouro))", $qb->expr()->literal('%' . strtolower($term) . '%'))
                ));
                $qb->setParameter('codUf', (int) $request->get('codUf'));
                $qb->setParameter('codMunicipio', (int) $request->get('codMunicipio'));
                $qb->setParameter('codTrecho', (int) $term);
                $qb->orderBy('o.codTrecho', 'ASC');
                return $qb;
            },
            'mapped' => false,
            'required' => true,
        );

        $fieldOptions['fkImobiliarioLote'] = array(
            'label' => 'label.imobiliarioLote.lote',
            'class' => Lote::class,
            'placeholder' => 'label.selecione',
            'mapped' => false,
            'required' => true,
            'query_builder' => function (EntityRepository $er) {
                return $er->createQueryBuilder('o')
                    ->leftJoin('o.fkImobiliarioBaixaLotes', 'b')
                    ->where('b.dtInicio is not null')
                    ->andWhere('b.dtTermino is not null')
                    ->orWhere('b.dtInicio is null')
                    ->orderBy('o.codLote', 'ASC');
            },
            'attr' => array(
                'class' => 'select2-parameters '
            )
        );

        $fieldOptions['descricao'] = array(
            'label' => 'label.imobiliarioLote.descricao',
            'mapped' => false,
            'required' => true
        );

        $fieldOptions['fkImobiliarioConfrontacoes'] = array(
            'label' => false,
            'mapped' => false,
            'template' => 'TributarioBundle::Imobiliario/Lote/confrontacoes.html.twig',
            'attr' => array(
                'style' => 'display:none;'
            ),
            'data' => array(
                'confrontacoes' => array()
            )
        );

        $fieldOptions['dtAglutinacao'] = array(
            'label' => 'label.imobiliarioLote.dtAglutinacao',
            'mapped' => false,
            'required' => true,
            'format' => 'dd/MM/yyyy'
        );

        $fieldOptions['profundidadeMedia'] = array(
            'label' => 'label.imobiliarioLote.profundidadeMedia',
            'mapped' => false,
            'required' => true,
            'attr' => array(
                'class' => 'money '
            )
        );

        $fieldOptions['lote'] = array(
            'label' => 'label.imobiliarioLote.lote',
            'class' => Lote::class,
            'placeholder' => 'label.selecione',
            'mapped' => false,
            'required' => true,
            'query_builder' => function (EntityRepository $er) use ($lote) {
                $qb = $er->createQueryBuilder('o');
                if ($lote) {
                    if ($lote->getFkImobiliarioLoteUrbano()) {
                        $qb->leftJoin('o.fkImobiliarioLoteUrbano', 'u');
                        $qb->where('u.codLote is not null');
                    } else {
                        $qb->leftJoin('o.fkImobiliarioLoteRural', 'r');
                        $qb->where('r.codLote is not null');
                    }
                    $qb->andWhere('o.codLote != :codLote');
                    $qb->setParameter('codLote', $lote->getCodLote());
                    // Localização
                    $qb->innerJoin('o.fkImobiliarioLoteLocalizacao', 'l');
                    $qb->andWhere('l.codLocalizacao = :codLocalizacao');
                    $qb->setParameter('codLocalizacao', $lote->getFkImobiliarioLoteLocalizacao()->getCodLocalizacao());
                }
                // Parcelado
                $qb->leftJoin('o.fkImobiliarioLoteParcelados', 'p');
                $qb->andWhere($qb->expr()->orX(
                    $qb->expr()->isNull('p.validado'),
                    $qb->expr()->eq('p.validado', 'true')
                ));
                // Baixa
                $qb->leftJoin('o.fkImobiliarioBaixaLotes', 'b');
                $qb->andWhere('b.dtInicio is not null AND b.dtTermino is not null OR b.dtInicio is null');
                $qb->orderBy('o.codLote', 'ASC');
                return $qb;
            },
            'attr' => array(
                'class' => 'select2-parameters '
            )
        );

        $fieldOptions['listaLotes'] = array(
            'label' => false,
            'mapped' => false,
            'template' => 'TributarioBundle::Imobiliario/Lote/lotes.html.twig',
            'attr' => array(
                'style' => 'display:none;'
            ),
            'data' => array()
        );


        if ($lote) {
            $fieldOptions['codLote']['data'] = $lote->getCodLote();
            $fieldOptions['codCadastro']['data'] = ($lote->getFkImobiliarioLoteUrbano())
                ? Cadastro::CADASTRO_TRIBUTARIO_LOTE_URBANO
                : Cadastro::CADASTRO_TRIBUTARIO_LOTE_RURAL;
            $proprietarios = (new LoteModel($em))->recuperaProprietariosLote($lote->getCodLote());
            $fieldOptions['codProprietario']['data'] = (count($proprietarios)) ? implode(',', $proprietarios) : '';
            $fieldOptions['fkImobiliarioConfrontacoes']['data'] = array(
                'confrontacoes' => $lote->getFkImobiliarioConfrontacoes()
            );
        }

        $formMapper->tab('label.imobiliarioLote.loteOriginal');
        $formMapper->with('label.imobiliarioLote.dadosLote');
        $formMapper->add('codLote', 'hidden', $fieldOptions['codLote']);
        $formMapper->add('codCadastro', 'hidden', $fieldOptions['codCadastro']);
        $formMapper->add('codProprietario', 'hidden', $fieldOptions['codProprietario']);
        $formMapper->add('dadosLote', 'customField', $fieldOptions['dadosLote']);
        $formMapper->add('profundidadeMedia', 'text', $fieldOptions['profundidadeMedia']);
        $formMapper->add('dtAglutinacao', 'sonata_type_date_picker', $fieldOptions['dtAglutinacao']);
        $formMapper->end();
        $formMapper->with('label.imobiliarioLote.processo');
        $formMapper->add('codClassificacao', 'entity', $fieldOptions['codClassificacao']);
        $formMapper->add('codAssunto', 'choice', $fieldOptions['codAssunto']);
        $formMapper->add('codProcesso', 'choice', $fieldOptions['codProcesso']);
        $formMapper->end();
        $formMapper->end();

        $formMapper->tab('label.imobiliarioLote.confrontacoes');
        $formMapper->with('label.imobiliarioLote.confrontacoes');
        $formMapper->add('fkImobiliarioPontoCardeal', 'entity', $fieldOptions['fkImobiliarioPontoCardeal']);
        $formMapper->add('confrontacaoTipo', 'choice', $fieldOptions['confrontacaoTipo']);
        $formMapper->add('extensao', 'text', $fieldOptions['extensao']);
        $formMapper->end();
        $formMapper->with('label.imobiliarioLote.trecho', array('class' => 'fieldset-trecho'));
        $formMapper->add('fkSwUf', 'entity', $fieldOptions['fkSwUf']);
        $formMapper->add('fkSwMunicipio', 'entity', $fieldOptions['fkSwMunicipio']);
        $formMapper->add('fkImobiliarioTrecho', 'autocomplete', $fieldOptions['fkImobiliarioTrecho']);
        $formMapper->add('principal', 'choice', $fieldOptions['principal']);
        $formMapper->end();
        $formMapper->with('');
        $formMapper->add('fkImobiliarioLote', 'entity', $fieldOptions['fkImobiliarioLote']);
        $formMapper->add('descricao', 'textarea', $fieldOptions['descricao']);
        $formMapper->add('fkImobiliarioConfrontacoes', 'customField', $fieldOptions['fkImobiliarioConfrontacoes']);
        $formMapper->end();
        $formMapper->end();

        $formMapper->tab('label.imobiliarioLote.lotes');
        $formMapper->with('label.imobiliarioLote.lotes');
        $formMapper->add('lote', 'entity', $fieldOptions['lote']);
        $formMapper->add('listaLotes', 'customField', $fieldOptions['listaLotes']);
        $formMapper->end();
        $formMapper->end();

        $formMapper->tab('label.imobiliarioLote.caracteristicas');
        $formMapper->with('label.imobiliarioLote.atributos', array('class' => 'atributoDinamicoWith'));
        $formMapper->add('atributosDinamicos', 'text', array('mapped' => false, 'required' => false));
        $formMapper->end();
        $formMapper->end();

        $admin = $this;
        $formMapper->getFormBuilder()->addEventListener(
            FormEvents::PRE_SUBMIT,
            function (FormEvent $event) use ($formMapper, $admin) {
                $form = $event->getForm();
                $data = $event->getData();

                if ($form->has('codAssunto')) {
                    $form->remove('codAssunto');
                }

                if (isset($data['codClassificacao']) && $data['codClassificacao'] != "") {
                    $codAssunto = $formMapper->getFormBuilder()->getFormFactory()->createNamed(
                        'codAssunto',
                        'entity',
                        null,
                        array(
                            'class' => SwAssunto::class,
                            'label' => 'label.imobiliarioLote.assunto',
                            'choice_value' => 'codComposto',
                            'placeholder' => 'label.selecione',
                            'mapped' => false,
                            'auto_initialize' => false,
                            'query_builder' => function (EntityRepository $er) use ($data) {
                                return $er->createQueryBuilder('o')
                                    ->where('o.codClassificacao = :codClassificacao')
                                    ->setParameter('codClassificacao', $data['codClassificacao']);
                            },
                            'attr' => array(
                                'class' => 'select2-parameters'
                            )
                        )
                    );

                    $form->add($codAssunto);
                }

                if ($form->has('codProcesso')) {
                    $form->remove('codProcesso');
                }

                if (isset($data['codAssunto']) && $data['codAssunto'] != "") {
                    $codProcesso = $formMapper->getFormBuilder()->getFormFactory()->createNamed(
                        'codProcesso',
                        'entity',
                        null,
                        array(
                            'class' => SwProcesso::class,
                            'label' => 'label.imobiliarioLote.assunto',
                            'choice_value' => 'codComposto',
                            'placeholder' => 'label.selecione',
                            'mapped' => false,
                            'auto_initialize' => false,
                            'query_builder' => function (EntityRepository $er) use ($data) {
                                list($codClassificacao, $codAssunto) = explode('~', $data['codAssunto']);
                                return $er->createQueryBuilder('o')
                                    ->where('o.codClassificacao = :codClassificacao')
                                    ->andWhere('o.codAssunto = :codAssunto')
                                    ->setParameters([
                                        'codClassificacao' => $codClassificacao,
                                        'codAssunto' => $codAssunto,
                                    ]);
                            },
                            'attr' => array(
                                'class' => 'select2-parameters'
                            )
                        )
                    );

                    $form->add($codProcesso);
                }
            }
        );
    }

    /**
     * @param Lote $object
     */
    public function prePersist($object)
    {
        $container = $this->getConfigurationPool()->getContainer();

        $dtAtual = new DateTimeMicrosecondPK();

        try {
            /** @var EntityManager $em */
            $em = $this->modelManager->getEntityManager($this->getClass());

            $loteModel = new LoteModel($em);

            /** @var Lote $lote */
            $lote = $this->getLote();

            $atributosDinamicos = $this->request->request->get('atributoDinamico');
            $lotes = $this->request->request->get('lotes');
            $confrontacoes = $this->request->request->get('confrontacoes');
            $confrontacoesOld = ($this->request->request->get('confrontacoes_old')) ? $this->request->request->get('confrontacoes_old') : array();

            $loteModel->salvarLoteAglutinacao($lote, $this->getForm(), $lotes, $confrontacoes, $confrontacoesOld, $atributosDinamicos, $dtAtual);

            $container->get('session')->getFlashBag()->add('success', $this->getTranslator()->trans('label.imobiliarioLote.msgAglutinar'));
            $this->forceRedirect('/tributario/cadastro-imobiliario/lote/list');
        } catch (\Exception $e) {
            $container->get('session')->getFlashBag()->add('error', $this->getTranslator()->trans('contactSupport'));
            $this->forceRedirect('/tributario/cadastro-imobiliario/lote/list');
        }
    }
}

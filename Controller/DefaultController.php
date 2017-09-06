<?php

namespace Ibnab\Bundle\PmanagerBundle\Controller;

use Symfony\Component\Security\Core\Util\ClassUtils;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Oro\Bundle\SecurityBundle\Annotation\Acl;
use Oro\Bundle\SecurityBundle\Annotation\AclAncestor;
use Ibnab\Bundle\PmanagerBundle\Entity\PDFTemplate;
use Symfony\Component\Routing\RouterInterface;
use Oro\Bundle\AttachmentBundle\Entity\Attachment;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Akeneo\Component\FileStorage\Model\FileInfo;
use Pim\Component\Catalog\FileStorage;
class DefaultController extends Controller {

    const CONTACT_ENTITY_NAME = 'OroCRM\Bundle\ContactBundle\Entity\Contact';
    const ORDER_ENTITY_NAME = 'OroCRM\Bundle\MagentoBundle\Entity\Order';

    /**
     * @Acl(
     *      id="pmanager_default_index",
     *      type="entity",
     *      class="IbnabPmanagerBundle:PDFTemplate",
     *      permission="EDIT"
     * )
     * @Route("/pmanager/default/index", name="pmanager_default_index")
     */
    public function indexAction() {
        $templateId = $this->get('request')->get('template_id');
        $productId = $this->get('request')->get('id');
        if ($productId == '' || $templateId == '' || $templateId == 'undefined' || $productId == 'undefined') {
            return new JsonResponse('', 403);
        }
        $product = $this->container->get('pim_catalog.repository.product')->findOneById($productId);
        $template = $this->get('doctrine.orm.entity_manager')->getRepository('IbnabPmanagerBundle:PDFTemplate')->findOneById($templateId);
        $twig = clone $this->get('twig');
        $pdfReneferer = $this->get('ibnab_pdf_generator.renderer.product_pdf');
        $preRenderAndFilter = $pdfReneferer->preRenderAndFilter($product,'pdf', [
                    'locale'        => $this->get('request')->get('dataLocale', null),
                    'scope'         => $this->get('request')->get('dataScope', null)
                ],
                $template->getContent()
        );
        $templateFromTwig = $this->get('twig')->createTemplate($preRenderAndFilter);
        $renderedHTML = $pdfReneferer->render($product,'pdf', [
                    'locale'        => $this->get('request')->get('dataLocale', null),
                    'scope'         => $this->get('request')->get('dataScope', null)
                ],
                $templateFromTwig
        );
        //var_dump($internResult);die();

       
        /*$twig->setLoader(new \Twig_Loader_String());
        $renderedHTML = $twig->render(
                $template->getContent(), array("product" => $product)
        );*/
        $this->resultPDF($template, $renderedHTML,$product);
    }

    protected function instancePDF($templateResult) {
        $configProvider = $this->getConfigurationProvider();
        $orientation = $templateResult->getOrientation() ? $templateResult->getOrientation() : 'P';
        $direction = $templateResult->getDirection() ? $templateResult->getDirection() : 'ltr';
        $font = $templateResult->getFont() ? $templateResult->getFont() : 'helvetica';
        $unit = $templateResult->getUnit() ? $templateResult->getUnit() : 'mm';
        $format = $templateResult->getFormat() ? $templateResult->getFormat() : 'A4';
        $right = $templateResult->getMarginright() ? $templateResult->getMarginright() : '2';
        $top = $templateResult->getMargintop() ? $templateResult->getMargintop() : '2';
        $left = $templateResult->getMarginleft() ? $templateResult->getMarginleft() : '2';
        $bottom = $templateResult->getMarginBottom() ? $templateResult->getMarginBottom() : '2';
        /*
          $header = $templateResult->getHeader() ? $templateResult->getHeader() : Null;
          $footer = $templateResult->getFooter() ? $templateResult->getFooter () : Null;
          if(!is_null($header)){
          $resultForPDFHeader = $this->get('ibnab_pmanager.pdftemplate_renderer')
          ->renderWithDefaultFilters($header->getContent(), null);
          } */
        if ($templateResult->getAutobreak() == 1) {
            $autobreak = true;
        } else {
            $autobreak = false;
        }

        $pdfObj = $this->get("ibnab_pmanager.tcpdf")->create($orientation, $unit, $format, true, 'UTF-8', false);
        if ($direction == 'rtl'):
            $pdfObj->setRTL(true);
        else:
            $pdfObj->setRTL(false);
        endif;
        if ($templateResult->getHf()) {

            $logo = $configProvider->get('ibnab_pmanager.logoupload');
            $logoSize = $configProvider->get('ibnab_pmanager.logosize');
            $textHeader = $configProvider->get('ibnab_pmanager.textheader');
            $titleHeader = $configProvider->get('ibnab_pmanager.titleheader');
            if ($logo != "") {
                $pdfObj->SetHeaderData($this->get('kernel')->getRootDir().'/../web/media/pmanager/' . $logo, $logoSize, $titleHeader, $textHeader);
            }

            $marginHeader = $configProvider->get('ibnab_pmanager.marginheader');
            $marginFooter = $configProvider->get('ibnab_pmanager.marginfooter');

            $pdfObj->SetHeaderMargin($marginHeader);
            $pdfObj->SetFooterMargin($marginFooter);
        }
        
        $pdfObj->setImageScale(1.25);
        $pdfObj->SetFont($font);
        $pdfObj->SetCreator($templateResult->getAuteur());
        $pdfObj->SetAuthor($templateResult->getAuteur());
        $pdfObj->SetMargins($left, $top, $right);
        $pdfObj->SetAutoPageBreak($autobreak, $bottom);
        return $pdfObj;
    }


    protected function getConfigurationProvider() {
        return $this->get('oro_config.global');
    }

    protected function resultPDF($templateResult, $templateHTML,$product) {
         $renderingDate = new \DateTime('now');
        $pdfObj = $this->instancePDF($templateResult);
        $pdfObj->setFontSubsetting(false);
        $pdfObj->AddPage();
        $outputFormat = 'pdf';
        $resultForPDF = $templateHTML;
        $resultForPDF = $templateResult->getCss() . $resultForPDF;

        $responseData['resultForPDF'] = $resultForPDF;

        $pdfObj->writeHTML($responseData['resultForPDF'], true, 0, true, 0);
        $pdfObj->lastPage();

        //substr($info['entityClass'], strrpos($str, '\\') + 1)
        //$fileName = $this->getFileOpertator()
                //->generateTemporaryFileName($responseData['entityId'], $outputFormat);
        //echo $this->getFileOpertator()->getTemporaryDirectory();die();

       $pdfObj->Output($product->getIdentifier().'-'.$renderingDate->format('Y-m-d_H-i-s').'.pdf', 'D');
       //return new JsonResponse('1');

    }


}

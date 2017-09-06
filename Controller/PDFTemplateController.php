<?php

namespace Ibnab\Bundle\PmanagerBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Oro\Bundle\SecurityBundle\Annotation\Acl;
use Oro\Bundle\SecurityBundle\Annotation\AclAncestor;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Ibnab\Bundle\PmanagerBundle\Entity\PDFTemplate;
use Symfony\Component\HttpFoundation\JsonResponse;

class PDFTemplateController extends Controller {

    /**
     * @Route("/pmanager/template/index", name="pmanager_template_index")
     * @Template()
     * @Acl(
     *      id="pmanager_template_index",
     *      type="entity",
     *      class="IbnabPmanagerBundle:PDFTemplate",
     *      permission="VIEW"
     * )
     */
    public function indexAction() {
        return array();
    }

    /**
     * @Route("/pmanager/template/logoupload", name="pmanager_template_logoupload")
     * @Template()
     * @Acl(
     *      id="pmanager_template_logoupload",
     *      type="entity",
     *      class="IbnabPmanagerBundle:PDFTemplate",
     *      permission="VIEW"
     * )
     */
    public function logouploadAction() {
        if (0 < $_FILES['file']['error']) {
            echo 'Error: ' . $_FILES['file']['error'] . '<br>';
            return new JsonResponse(0);
        } else {
            
            if (!file_exists($this->get('kernel')->getRootDir().'/../web/media/pmanager/')) {
                mkdir($this->get('kernel')->getRootDir().'/../web/media/pmanager/', 0755, true);
            }
            if(move_uploaded_file($_FILES['file']['tmp_name'], $this->get('kernel')->getRootDir().'/../web/media/pmanager/' . $_FILES['file']['name']))
            {       
               return new JsonResponse(1);
            }
        }
        return new JsonResponse(0);
    }

    /**
     * @Route("pmanager/template/attributes", name="pmanager_template_attributes"))
     *
     * @AclAncestor("pmanager_template_attributes"))
     * @return Response
     */
    public function attributesAction() {
        $codes = $this->getRequest()->query->get('code');
        if ($codes == '') {
            return new JsonResponse('no code', 403);
        }
        $familyCodes = explode(',', $codes);
        $attributeCodes = array();
        $em = $this->get('doctrine.orm.entity_manager');
        $families = $this->container->get('pim_catalog.repository.family')->findBy(['code' => $familyCodes]);
        foreach ($families as $family) {
            $attributeCodes = array_merge($attributeCodes, $family->getAttributeCodes());
        }


        return new JsonResponse(array_unique($attributeCodes));
    }

    /**
     * @Route("pmanager/template/jsontemplate/{id}", name="pmanager_template_jsontemplate", requirements={"id"="\d+"}, defaults={"id"=0}))
     *
     * @Acl(
     *      id="pmanager_template_jsontemplate",
     *      type="entity",
     *      class="IbnabPmanagerBundle:PDFTemplate",
     *      permission="VIEW"
     * )
     * @return Response
     */
    public function jsontemplateAction($id) {
        if ($id == '') {
            return new JsonResponse('', 403);
        }
        $product = $this->container->get('pim_catalog.repository.product')->findOneById($id);
        if (!$product) {
            
        }
        $family = $product->getFamily();
        $code = $family->getCode();
        $templateResult = array();

        $templates = $this->get('doctrine.orm.entity_manager')->getRepository('IbnabPmanagerBundle:PDFTemplate')->getFamilyTemplatesQueryBuilder($code);
        //var_dump($templates);die();
        foreach ($templates as $template) {
            $templateResult[] = ["name" => $template->getName(), "id" => $template->getId()];
        }


        return new JsonResponse(array_unique($templateResult));
    }

    /**
     * @Route("pmanager/template/update/{id}", name="pmanager_template_update", requirements={"id"="\d+"}, defaults={"id"=0}))
     * @Acl(
     *      id="pmanager_template_update",
     *      type="entity",
     *      class="IbnabPmanagerBundle:PDFTemplate",
     *      permission="EDIT"
     * )
     * @Template()
     */
    public function updateAction(PDFTemplate $entity, $isClone = false) {
        return $this->update($entity, $isClone);
    }

    /**
     * @Route("pmanager/template/delete/{id}", name="pmanager_template_delete", requirements={"id"="\d+"}, defaults={"id"=0}))
     * @param int $id
     *
     * @Acl(
     *      id="pmanager_template_delete",
     *      type="entity",
     *      class="IbnabPmanagerBundle:PDFTemplate",
     *      permission="DELETE"
     * )
     *
     * @return Response
     */
    public function deleteAction($id) {
        $em = $this->get('doctrine.orm.entity_manager');
        $entity = $em->getRepository('Ibnab\Bundle\PmanagerBundle\Entity\PDFTemplate')->find($id);
        if (!$entity) {
            return new JsonResponse('', 403);
        }



        $em->remove($entity);
        $em->flush();

        return new JsonResponse('', 204);
    }

    /**
     * @Route("pmanager/template/create", name="pmanager_template_create")
     * @Acl(
     *      id="pmanager_template_create",
     *      type="entity",
     *      class="IbnabPmanagerBundle:PDFTemplate",
     *      permission="CREATE"
     * )
     * @Template("IbnabPmanagerBundle:PDFTemplate:update.html.twig")
     */
    public function createAction() {
        return $this->update(new PDFTemplate());
    }

    /**
     * @Route("pmanager/template/clone/{id}" , name="pmanager_template_clone" , requirements={"id"="\d+"}, defaults={"id"=0}))
     * @AclAncestor("pmanager_template_create")
     * @Template("IbnabPmanagerBundle:PDFTemplate:update.html.twig")
     */
    public function cloneAction(PDFTemplate $entity) {
        return $this->update(clone $entity, true);
    }

    /**
     * @param EmailTemplate $entity
     * @param bool $isClone
     * @return array
     */
    protected function update(PDFTemplate $entity, $isClone = false) {
        if ($this->get('ibnab_pmanager.form.handler.pdftemplate')->process($entity)) {
            $this->get('session')->getFlashBag()->add(
                    'success', $this->get('translator')->trans('ibnab.pmanager.pdftemplate.saved.message')
            );

            return new RedirectResponse(
                    $this->get('router')->generate('pmanager_template_update', ['id' => $entity->getId()])
            );
        }

        return array(
            'entity' => $entity,
            'form' => $this->get('ibnab_pmanager.form.pdftemplate')->createView(),
            'isClone' => $isClone
        );
    }

}

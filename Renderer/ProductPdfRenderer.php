<?php

namespace Ibnab\Bundle\PmanagerBundle\Renderer;

use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Liip\ImagineBundle\Imagine\Data\DataManager;
use Liip\ImagineBundle\Imagine\Filter\FilterManager;
use Pim\Bundle\CatalogBundle\Entity\AttributeGroup;
use Pim\Bundle\PdfGeneratorBundle\Builder\PdfBuilderInterface;
use Pim\Component\Catalog\AttributeTypes;
use Pim\Component\Catalog\Model\AttributeInterface;
use Pim\Component\Catalog\Model\ProductInterface;
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Pim\Bundle\PdfGeneratorBundle\Renderer\RendererInterface;

/**
 * PDF renderer used to render PDF for a Product
 *
 * @author    Charles Pourcel <charles.pourcel@akeneo.com>
 * @copyright 2014 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class ProductPdfRenderer implements RendererInterface
{
    const PDF_FORMAT = 'pdf';

    const THUMBNAIL_FILTER = 'thumbnail';
    
    /** @var EngineInterface */
    protected $templating;

    /** @var PdfBuilderInterface */
    protected $pdfBuilder;

    /** @var DataManager */
    protected $dataManager;

    /** @var CacheManager */
    protected $cacheManager;

    /** @var FilterManager */
    protected $filterManager;

    /** @var string */
    protected $template;

    /** @var string */
    protected $uploadDirectory;
    protected $mediaDirectory;
    protected $imagePaths = null;
    /** @var string */
    protected $customFont;
    const VARIABLE_NOT_FOUND = 'oro.email.variable.not.found';
    /**
     * @param EngineInterface     $templating
     * @param PdfBuilderInterface $pdfBuilder
     * @param DataManager         $dataManager
     * @param CacheManager        $cacheManager
     * @param FilterManager       $filterManager
     * @param string              $template
     * @param string              $uploadDirectory
     * @param string|null         $customFont
     */
    public function __construct(
        EngineInterface $templating,
        PdfBuilderInterface $pdfBuilder,
        DataManager $dataManager,
        CacheManager $cacheManager,
        FilterManager $filterManager,
        $template,
        $uploadDirectory,
        $mediaDirectory,
        $customFont = null
    ) {
        $this->templating = $templating;
        $this->pdfBuilder = $pdfBuilder;
        $this->dataManager = $dataManager;
        $this->cacheManager = $cacheManager;
        $this->filterManager = $filterManager;
        $this->template = $template;
        $this->uploadDirectory = $uploadDirectory;
        $this->mediaDirectory = $mediaDirectory;
        $this->customFont = $customFont;
    }

    /**
     * {@inheritdoc}
     */
    public function render($object, $format, array $context = [],$templateFromTwig = null)
    {
        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);
        $params = array_merge(
            $context,
            [
                'product' => $object
            ]
        );
         
         
         if(!is_null($this->imagePaths)){
         $this->generateThumbnailsCache($this->imagePaths, self::THUMBNAIL_FILTER);
         }

         return $this->templating->render($templateFromTwig, $params);

    }
    public function preRenderAndFilter($object, $format, array $context = [],$template){
         $preFilter = $this->processDefaultFilters($template, $object,$context['locale'],$context['scope']);
         return $preFilter ;        
    }

    /**
     * {@inheritdoc}
     */
    public function supports($object, $format)
    {
        return $object instanceof ProductInterface && $format === static::PDF_FORMAT;
    }

    /**
     * Get attributes to display
     *
     * @param ProductInterface $product
     * @param string           $locale
     *
     * @return AttributeInterface[]
     */
    protected function getAttributes(ProductInterface $product, $locale)
    {
        return $product->getAttributes();
    }

    /**
     * get attributes grouped by attribute group
     *
     * @param ProductInterface $product
     * @param string           $locale
     *
     * @return AttributeGroup[]
     */
    protected function getGroupedAttributes(ProductInterface $product, $locale)
    {
        $groups = [];

        foreach ($this->getAttributes($product, $locale) as $attribute) {
            $groupLabel = $attribute->getGroup()->getLabel();
            if (!isset($groups[$groupLabel])) {
                $groups[$groupLabel] = [];
            }

            $groups[$groupLabel][$attribute->getCode()] = $attribute;
        }

        return $groups;
    }
    protected function processDefaultFilters($template, $entity, $locale = null , $scope = null)
    {
        
        $that     = $this;
        $attributes = $entity->getFamily()->getAttributes();
        $allcode = array();
        foreach($attributes as $attribute){
            $allcode[$attribute->getCode()] = $attribute;
        }
        $callback = function ($match) use ($entity, $that,$allcode,$locale,$scope) {
            $result = $match[0];
            $path   = $match[1];
            $split  = explode('.', $path);

            if ($split[0] && 'product' === $split[0]) {
                
                try {
                    if(array_key_exists($split[1],$allcode)){
                        $attribute = $allcode[$split[1]];
                        if($attribute->getType() == 'pim_catalog_price'){
                            return $entity->getValue($split[1],$locale,$scope);
                        }
                        elseif($attribute->getType() == 'pim_catalog_image'){
                            $that->imagePaths = $that->getImagePaths($entity, $locale, $scope);
                            $image = isset($that->imagePaths[0]) ? $that->imagePaths[0] : null;
                            //return $this->dataManager->find(self::THUMBNAIL_FILTER, $image);
                            //return $this->cacheManager->getBrowserPath($image,self::THUMBNAIL_FILTER);
                            //echo $this->mediaDirectory.'/cache/'.self::THUMBNAIL_FILTER.$image;die();
                            return $this->mediaDirectory.'/cache/'.self::THUMBNAIL_FILTER.'/'.$image;
                        }else{
                           return $entity->getValue($split[1],$locale,$scope); 
                        }
                    }
                    
                } catch (\Exception $e) {
                    $result = $e->getMessage();
                }
            }

            return $result;

        };

        return preg_replace_callback('/{{\s([\w\d\.\_\-]*?)\s}}/u', $callback, $template);
    }
    /**
     * Get all image paths
     *
     * @param ProductInterface $product
     * @param string           $locale
     * @param string           $scope
     *
     * @return AttributeInterface[]
     */
    protected function getImagePaths(ProductInterface $product, $locale, $scope)
    {
        $imagePaths = [];

        foreach ($this->getAttributes($product, $locale) as $attribute) {
            if (AttributeTypes::IMAGE === $attribute->getType()) {
                $media = $product->getValue($attribute->getCode(), $locale, $scope)->getMedia();

                if (null !== $media && null !== $media->getKey()) {
                    $imagePaths[] = $media->getKey();
                }
            }
        }
        return $imagePaths;
    }

    /**
     * Generate media thumbnails cache used by the PDF document
     *
     * @param string[] $imagePaths
     * @param string   $filter
     */
    protected function generateThumbnailsCache(array $imagePaths, $filter)
    {
        foreach ($imagePaths as $path) {
            if (!$this->cacheManager->isStored($path, $filter)) {
                $binary = $this->dataManager->find($filter, $path);
                $this->cacheManager->store(
                    $this->filterManager->applyFilter($binary, $filter),
                    $path,
                    $filter
                );
            }
        }
    }

    /**
     * Options configuration (for the option resolver)
     *
     * @param OptionsResolver $resolver
     */
    protected function configureOptions(OptionsResolver $resolver)
    {
        $resolver
            ->setRequired(['locale', 'scope', 'product'])
            ->setDefaults(
                [
                    'renderingDate' => new \DateTime(),
                    'filter'        => static::THUMBNAIL_FILTER,
                ]
            )
            ->setDefined(['groupedAttributes', 'imagePaths', 'customFont'])
        ;
    }
}

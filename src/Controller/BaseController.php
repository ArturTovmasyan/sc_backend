<?php

namespace App\Controller;

use App\Annotation\Grid;
use App\Model\ResponseCode;
use App\Exception\GridOptionsNotFoundException;
use App\Service\IGridService;
use App\Util\ArrayUtil;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Doctrine\Common\Annotations\Reader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class BaseController extends AbstractController
{
    /** @var SerializerInterface */
    protected $serializer;

    /** @var EntityManagerInterface */
    protected $em;

    /** @var ValidatorInterface */
    protected $validator;

    /** @var UserPasswordEncoderInterface */
    protected $encoder;

    /** @var Reader */
    protected $reader;

    /** @var Security */
    protected $security;

    /**
     * BaseController constructor.
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $em
     * @param ValidatorInterface $validator
     * @param UserPasswordEncoderInterface $encoder
     * @param Reader $reader
     * @param Security $security
     */
    public function __construct(
        SerializerInterface $serializer,
        EntityManagerInterface $em,
        ValidatorInterface $validator,
        UserPasswordEncoderInterface $encoder,
        Reader $reader,
        Security $security
    )
    {
        $this->serializer = $serializer;
        $this->em = $em;
        $this->validator = $validator;
        $this->encoder = $encoder;
        $this->reader = $reader;
        $this->security = $security;
    }

    /**
     * @param Request $request
     * @param string $entityName
     * @param string $groupName
     * @param IGridService $service
     * @param array $params
     * @return JsonResponse
     */
    protected function respondList(Request $request, string $entityName, string $groupName, IGridService $service, ...$params)
    {
        return $this->respondSuccess(
            Response::HTTP_OK,
            '',
            $service->list(count($params) > 0 ? $params[0] : []),
            [$groupName]
        );
    }

    /**
     * @param Request $request
     * @param string $entityName
     * @param string $groupName
     * @param IGridService $service
     * @param array $params
     * @return JsonResponse
     * @throws \ReflectionException
     * @throws \Throwable
     */
    protected function respondGrid(Request $request, string $entityName, string $groupName, IGridService $service, ...$params)
    {
        $queryBuilder = $this->getQueryBuilder($request, $entityName, $groupName);
        $service->gridSelect($queryBuilder, $params);

        $paginator = new Paginator($queryBuilder);

        $page = $request->get('page') ?: 1;
        $perPage = $request->get('per_page');

        $total = $paginator->count();

        $paginator
            ->getQuery()
            ->setFirstResult($perPage * ($page - 1))
            ->setMaxResults($perPage);

        $data = [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'data' => $paginator->getQuery()->getArrayResult()
        ];

        $serializationContext = SerializationContext::create()->setSerializeNull(true);

        if (!empty($groupName)) {
            $serializationContext->setGroups([$groupName]);
        }

        $responseData = $this->serializer->serialize($data, 'json', $serializationContext);


        return new JsonResponse($responseData, Response::HTTP_OK, [], true);
    }

    /**
     * @param string $message
     * @param int $httpStatus
     * @param array $data
     * @param array $groups
     * @param array $headers
     * @return JsonResponse
     */
    protected function respondSuccess($httpStatus = Response::HTTP_OK, $message = '', $data = [], $groups = [], $headers = [])
    {
        $responseData = [];

        if (!empty($message)) {
            $responseData['message'] = $message;
        } elseif (isset(ResponseCode::$titles[$httpStatus])) {
            $responseData['code'] = $httpStatus;
            $responseData['message'] = ResponseCode::$titles[$httpStatus]['message'];
            $httpStatus = ResponseCode::$titles[$httpStatus]['httpCode'];
        }

        $serializationContext = SerializationContext::create()->setSerializeNull(true);

        if (!empty($groups)) {
            $serializationContext->setGroups($groups);
        }

        $responseData = $this->serializer->serialize($data, 'json', $serializationContext);

        return new JsonResponse($responseData, $httpStatus, $headers, true);
    }

    /**
     * @param string $entityName
     * @param string $groupName
     * @return JsonResponse
     * @throws \ReflectionException
     */
    protected function getOptionsByGroupName(string $entityName, string $groupName)
    {
        $options = $this->getGrid($entityName)->getGroupOptions($groupName);

        if (!$options) {
            throw new GridOptionsNotFoundException();
        }

        $options = ArrayUtil::remove_keys($options, ['field']);

        foreach ($options as &$option) {
            $option['type'] = explode(":", $option['type'])[0];
        }

        return $this->respondSuccess(
            Response::HTTP_OK,
            '',
            $options
        );
    }

    /**
     * @param Request $request
     * @param string $entityName
     * @param string $groupName
     * @return QueryBuilder
     * @throws \ReflectionException
     * @throws \Throwable
     */
    protected function getQueryBuilder(Request $request, string $entityName, string $groupName)
    {
        return $this->getGrid($entityName)
            ->setEntityManager($this->em)
            ->renderByGroup($request->query->all(), $groupName)
            ->getQueryBuilder();
    }

    /**
     * @param $entityName
     * @return null|object|Grid
     * @throws \ReflectionException
     */
    private function getGrid($entityName)
    {
        /**
         * @var Grid $annotation
         */
        $reflectionClass = new \ReflectionClass($entityName);

        return $this->reader->getClassAnnotation($reflectionClass, Grid::class);
    }
}

<?php

namespace Lunches\Controller;

use Lunches\Exception\LineItemException;
use Lunches\Exception\RuntimeException;
use Lunches\Exception\ValidationException;
use Lunches\Model\DateRange;
use Lunches\Model\Order;
use Lunches\Model\OrderFactory;
use Lunches\Model\OrderRepository;
use Lunches\Model\Transaction;
use Doctrine\ORM\EntityManager;
use Lunches\Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class OrdersController
 */
class OrdersController extends ControllerAbstract
{
    /** @var EntityManager */
    protected $em;

    /** @var OrderRepository */
    protected $repo;

    /** @var OrderFactory  */
    protected $orderFactory;

    /** @var string  */
    protected $orderClass;

    /**
     * OrdersController constructor.
     *
     * @param EntityManager $em
     * @param OrderFactory $orderFactory
     */
    public function __construct(EntityManager $em, OrderFactory $orderFactory)
    {
        $this->orderClass = '\Lunches\Model\Order';
        $this->orderFactory = $orderFactory;
        $this->em = $em;
        $this->repo = $em->getRepository($this->orderClass);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getList(Request $request)
    {
        try {
            $shipmentDate = $request->get('shipmentDate') ? new \DateTime($request->get('shipmentDate')) : null;
            $dateRange = $this->createDateRange($request, false, false);
            $filters = array_filter([
                'shipmentDate' => $shipmentDate,
                'dateRange' => $dateRange,
            ]);
        } catch (\Exception $e) {
            return $this->failResponse('Invalid filters: ' . $e->getMessage(), 400);
        }

        if (count($filters) === 0) {
            return $this->failResponse('Provide one or more filters to obtain orders');
        }

        $orders = $this->repo->getList($filters);

        $orders = array_map(function (Order $order) {
            return $order->toArray();
        }, $orders);

        return $this->successResponse($orders);
    }

    /**
     * @param int $orderId
     * @return JsonResponse
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\NoResultException
     */
    public function get($orderId)
    {
        $order = $this->repo->find($orderId);
        if (!$order) {
            return $this->failResponse('Order not found', 404);
        }
        return $this->successResponse($order->toArray());
    }

    public function getByUser($user, Request $request)
    {
        $range = $this->createDateRange($request);
        $orders = $this->repo->findByUsername($user, $request->get('paid', null), $request->get('withCanceled', 0), $range);
        if (!count($orders)) {
            return $this->failResponse('Orders not found', 404);
        }

        return $this->successResponse(array_map(function(Order $order) {
            return $order->toArray();
        }, $orders));
    }

    /**
     * @param Request $request
     * @param Application $app
     * @return Response
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\ORMInvalidArgumentException
     * @throws \Lunches\Exception\RuntimeException
     * @throws \InvalidArgumentException
     */
    public function create(Request $request, Application $app)
    {
        $data = (array) $request->request->all();

        try {
            $order = $this->orderFactory->createNewFromArray($data);
            $transaction = $order->pay();
            if ($transaction instanceof Transaction) {
                $this->em->persist($transaction);
            }
            $this->em->persist($order);
            $this->em->flush();

            return $this->successResponse($order->toArray(), 201, [
                'Location' => $app->url('order', ['orderId' => $order->getId()])
            ]);

        } catch (ValidationException $e) {
            $errors['order'] = $e->getMessage();
        } catch (RuntimeException $e) {
            $errors['order'] = $e->getMessage();
        } catch (LineItemException $e) {
            $errors['order'] = $e->getMessage();
        }

        return $this->failResponse('Invalid input data provided', 400, $errors);
    }

    public function cancel($orderId, Request $request)
    {
        /** @var Order $order */
        $order = $this->repo->find($orderId);
        if (!$order) {
            return $this->failResponse('Order not found', 404);
        }
        try {
            $transaction = $order->cancel($request->get('reason'));
            if ($transaction instanceof Transaction) {
                $this->em->persist($transaction);
            }
            $this->em->flush();
        } catch (\Exception $e) {
            return $this->failResponse($e->getMessage(), 400);
        }
        return $this->successResponse($order->toArray());
    }
    public function reject($orderId, Request $request, Application $app)
    {
        if (!$this->isAccessTokenValid($request, $app)) {
            return $this->authResponse();
        }
        /** @var Order $order */
        $order = $this->repo->find($orderId);
        if (!$order) {
            return $this->failResponse('Order not found', 404);
        }
        try {
            $transaction = $order->reject($request->get('reason'));
            if ($transaction instanceof Transaction) {
                $this->em->persist($transaction);
            }
            $this->em->flush();
        } catch (\Exception $e) {
            return $this->failResponse($e->getMessage(), 400);
        }
        return $this->successResponse($order->toArray());
    }
    public function update($orderId, Request $request)
    {
        /** @var Order $order */
        $order = $this->repo->find($orderId);
        if (!$order) {
            return $this->failResponse('Order not found', 404);
        }
        $address = $request->get('address');
        try {
            if ($address) {
                $order->changeAddress($address);
                $this->em->flush();
            }
        } catch (ValidationException $e) {
            return $this->failResponse($e->getMessage(), 400);
        }

        return new JsonResponse($order->toArray());
    }

    private function createDateRange(Request $request, $required = false, $default = true)
    {
        $start = $request->get('startDate');
        if (!$start && $default === true) {
            $start = new \DateTime('monday last week');
        }
        $end = $request->get('endDate');
        if (!$end && $default === true) {
            $end = new \DateTime('friday next week');
        }
        if (!$required && !($start || $end)) {
            return null;
        }

        return new DateRange($start, $end);
    }
}

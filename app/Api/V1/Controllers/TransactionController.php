<?php

/**
 * TransactionController.php
 * Copyright (c) 2019 james@firefly-iii.org
 *
 * This file is part of Firefly III (https://github.com/firefly-iii).
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace FireflyIII\Api\V1\Controllers;

use FireflyIII\Api\V1\Requests\TransactionStoreRequest;
use FireflyIII\Api\V1\Requests\TransactionUpdateRequest;
use FireflyIII\Events\StoredTransactionGroup;
use FireflyIII\Events\UpdatedTransactionGroup;
use FireflyIII\Exceptions\DuplicateTransactionException;
use FireflyIII\Exceptions\FireflyException;
use FireflyIII\Helpers\Collector\GroupCollectorInterface;
use FireflyIII\Models\TransactionGroup;
use FireflyIII\Models\TransactionJournal;
use FireflyIII\Repositories\Journal\JournalAPIRepositoryInterface;
use FireflyIII\Repositories\Journal\JournalRepositoryInterface;
use FireflyIII\Repositories\TransactionGroup\TransactionGroupRepositoryInterface;
use FireflyIII\Support\Http\Api\TransactionFilter;
use FireflyIII\Transformers\AttachmentTransformer;
use FireflyIII\Transformers\PiggyBankEventTransformer;
use FireflyIII\Transformers\TransactionGroupTransformer;
use FireflyIII\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use League\Fractal\Pagination\IlluminatePaginatorAdapter;
use League\Fractal\Resource\Collection as FractalCollection;
use League\Fractal\Resource\Item;
use Log;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class TransactionController
 */
class TransactionController extends Controller
{
    use TransactionFilter;

    /** @var TransactionGroupRepositoryInterface Group repository. */
    private $groupRepository;
    /** @var JournalAPIRepositoryInterface Journal API repos */
    private $journalAPIRepository;
    /** @var JournalRepositoryInterface The journal repository */
    private $repository;

    /**
     * TransactionController constructor.
     *
     * @codeCoverageIgnore
     */
    public function __construct()
    {
        parent::__construct();
        $this->middleware(
            function ($request, $next) {
                /** @var User $admin */
                $admin = auth()->user();

                $this->repository           = app(JournalRepositoryInterface::class);
                $this->groupRepository      = app(TransactionGroupRepositoryInterface::class);
                $this->journalAPIRepository = app(JournalAPIRepositoryInterface::class);
                $this->repository->setUser($admin);
                $this->groupRepository->setUser($admin);
                $this->journalAPIRepository->setUser($admin);

                return $next($request);
            }
        );
    }

    /**
     * @param TransactionGroup $transactionGroup
     *
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function attachments(TransactionGroup $transactionGroup): JsonResponse
    {
        $manager     = $this->getManager();
        $attachments = new Collection;
        foreach ($transactionGroup->transactionJournals as $transactionJournal) {
            $attachments = $this->journalAPIRepository->getAttachments($transactionJournal)->merge($attachments);
        }

        /** @var AttachmentTransformer $transformer */
        $transformer = app(AttachmentTransformer::class);
        $transformer->setParameters($this->parameters);

        $resource = new FractalCollection($attachments, $transformer, 'attachments');

        return response()->json($manager->createData($resource)->toArray())->header('Content-Type', 'application/vnd.api+json');

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param TransactionGroup $transactionGroup
     *
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function delete(TransactionGroup $transactionGroup): JsonResponse
    {
        $this->repository->destroyGroup($transactionGroup);

        return response()->json([], 204);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param TransactionJournal $transactionJournal
     *
     * @codeCoverageIgnore
     * @return JsonResponse
     */
    public function deleteJournal(TransactionJournal $transactionJournal): JsonResponse
    {
        $this->repository->destroyJournal($transactionJournal);

        return response()->json([], 204);
    }

    /**
     * Show all transactions.
     *
     * @param Request $request
     *
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function index(Request $request): JsonResponse
    {
        $pageSize = (int)app('preferences')->getForUser(auth()->user(), 'listPageSize', 50)->data;
        $type     = $request->get('type') ?? 'default';
        $this->parameters->set('type', $type);

        $types   = $this->mapTransactionTypes($this->parameters->get('type'));
        $manager = $this->getManager();
        /** @var User $admin */
        $admin = auth()->user();

        // use new group collector:
        /** @var GroupCollectorInterface $collector */
        $collector = app(GroupCollectorInterface::class);
        $collector
            ->setUser($admin)
            // all info needed for the API:
            ->withAPIInformation()
            // set page size:
            ->setLimit($pageSize)
            // set page to retrieve
            ->setPage($this->parameters->get('page'))
            // set types of transactions to return.
            ->setTypes($types);


        if (null !== $this->parameters->get('start') && null !== $this->parameters->get('end')) {
            $collector->setRange($this->parameters->get('start'), $this->parameters->get('end'));
        }
        $paginator = $collector->getPaginatedGroups();
        $paginator->setPath(route('api.v1.transactions.index') . $this->buildParams());
        $transactions = $paginator->getCollection();

        /** @var TransactionGroupTransformer $transformer */
        $transformer = app(TransactionGroupTransformer::class);
        $transformer->setParameters($this->parameters);

        $resource = new FractalCollection($transactions, $transformer, 'transactions');
        $resource->setPaginator(new IlluminatePaginatorAdapter($paginator));

        return response()->json($manager->createData($resource)->toArray())->header('Content-Type', 'application/vnd.api+json');
    }

    /**
     * @param TransactionGroup $transactionGroup
     *
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function piggyBankEvents(TransactionGroup $transactionGroup): JsonResponse
    {
        $manager = $this->getManager();
        $events  = new Collection;
        foreach ($transactionGroup->transactionJournals as $transactionJournal) {
            $events = $this->journalAPIRepository->getPiggyBankEvents($transactionJournal)->merge($events);
        }

        /** @var PiggyBankEventTransformer $transformer */
        $transformer = app(PiggyBankEventTransformer::class);
        $transformer->setParameters($this->parameters);

        $resource = new FractalCollection($events, $transformer, 'piggy_bank_events');

        return response()->json($manager->createData($resource)->toArray())->header('Content-Type', 'application/vnd.api+json');

    }

    /**
     * Show a single transaction.
     *
     * @param TransactionGroup $transactionGroup
     *
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function show(TransactionGroup $transactionGroup): JsonResponse
    {
        $manager = $this->getManager();
        /** @var User $admin */
        $admin = auth()->user();
        // use new group collector:
        /** @var GroupCollectorInterface $collector */
        $collector = app(GroupCollectorInterface::class);
        $collector
            ->setUser($admin)
            // filter on transaction group.
            ->setTransactionGroup($transactionGroup)
            // all info needed for the API:
            ->withAPIInformation();

        $selectedGroup = $collector->getGroups()->first();
        if (null === $selectedGroup) {
            throw new NotFoundHttpException();
        }
        /** @var TransactionGroupTransformer $transformer */
        $transformer = app(TransactionGroupTransformer::class);
        $transformer->setParameters($this->parameters);
        $resource = new Item($selectedGroup, $transformer, 'transactions');

        return response()->json($manager->createData($resource)->toArray())->header('Content-Type', 'application/vnd.api+json');
    }

    /**
     * Show a single transaction, by transaction journal.
     *
     * @param TransactionJournal $transactionJournal
     *
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function showByJournal(TransactionJournal $transactionJournal): JsonResponse
    {
        return $this->show($transactionJournal->transactionGroup);
    }

    /**
     * Store a new transaction.
     *
     * @param TransactionStoreRequest $request
     *
     * @return JsonResponse
     */
    public function store(TransactionStoreRequest $request): JsonResponse
    {
        Log::debug('Now in API TransactionController::store()');
        $data         = $request->getAll();
        $data['user'] = auth()->user()->id;

        Log::channel('audit')
           ->info('Store new transaction over API.', $data);

        try {
            $transactionGroup = $this->groupRepository->store($data);
        } catch (DuplicateTransactionException $e) {
            Log::warning('Caught a duplicate. Return error message.');
            // return bad validation message.
            // TODO use Laravel's internal validation thing to do this.
            $response = [
                'message' => 'The given data was invalid.',
                'errors'  => [
                    'transactions.0.description' => [$e->getMessage()],
                ],
            ];

            return response()->json($response, 422);
        } catch(FireflyException $e) {
            Log::warning('Caught an exception. Return error message.');
            Log::error($e->getMessage());
            // return bad validation message.
            // TODO use Laravel's internal validation thing to do this.
            $response = [
                'message' => 'The given data was invalid.',
                'errors'  => [
                    'transactions.0.description' => [sprintf('Internal exception: %s',$e->getMessage())],
                ],
            ];

            return response()->json($response, 422);
        }
        app('preferences')->mark();
        event(new StoredTransactionGroup($transactionGroup, $data['apply_rules'] ?? true));

        $manager = $this->getManager();
        /** @var User $admin */
        $admin = auth()->user();
        // use new group collector:
        /** @var GroupCollectorInterface $collector */
        $collector = app(GroupCollectorInterface::class);
        $collector
            ->setUser($admin)
            // filter on transaction group.
            ->setTransactionGroup($transactionGroup)
            // all info needed for the API:
            ->withAPIInformation();

        $selectedGroup = $collector->getGroups()->first();
        if (null === $selectedGroup) {
            throw new NotFoundHttpException(); // @codeCoverageIgnore
        }
        /** @var TransactionGroupTransformer $transformer */
        $transformer = app(TransactionGroupTransformer::class);
        $transformer->setParameters($this->parameters);
        $resource = new Item($selectedGroup, $transformer, 'transactions');

        return response()->json($manager->createData($resource)->toArray())->header('Content-Type', 'application/vnd.api+json');
    }


    /**
     * Update a transaction.
     *
     * @param TransactionUpdateRequest $request
     * @param TransactionGroup         $transactionGroup
     *
     * @return JsonResponse
     */
    public function update(TransactionUpdateRequest $request, TransactionGroup $transactionGroup): JsonResponse
    {
        Log::debug('Now in update routine.');
        $data             = $request->getAll();
        $transactionGroup = $this->groupRepository->update($transactionGroup, $data);
        $manager          = $this->getManager();

        app('preferences')->mark();
        event(new UpdatedTransactionGroup($transactionGroup, $data['apply_rules'] ?? true));

        /** @var User $admin */
        $admin = auth()->user();
        // use new group collector:
        /** @var GroupCollectorInterface $collector */
        $collector = app(GroupCollectorInterface::class);
        $collector
            ->setUser($admin)
            // filter on transaction group.
            ->setTransactionGroup($transactionGroup)
            // all info needed for the API:
            ->withAPIInformation();

        $selectedGroup = $collector->getGroups()->first();
        if (null === $selectedGroup) {
            throw new NotFoundHttpException(); // @codeCoverageIgnore
        }
        /** @var TransactionGroupTransformer $transformer */
        $transformer = app(TransactionGroupTransformer::class);
        $transformer->setParameters($this->parameters);
        $resource = new Item($selectedGroup, $transformer, 'transactions');

        return response()->json($manager->createData($resource)->toArray())->header('Content-Type', 'application/vnd.api+json');

    }
}

<?php
/**
 * ImportController.php
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

use FireflyIII\Helpers\Collector\GroupCollectorInterface;
use FireflyIII\Models\ImportJob;
use FireflyIII\Repositories\ImportJob\ImportJobRepositoryInterface;
use FireflyIII\Support\Http\Api\TransactionFilter;
use FireflyIII\Transformers\ImportJobTransformer;
use FireflyIII\Transformers\TransactionGroupTransformer;
use FireflyIII\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use League\Fractal\Pagination\IlluminatePaginatorAdapter;
use League\Fractal\Resource\Collection as FractalCollection;
use League\Fractal\Resource\Item;

/**
 * Class ImportController
 */
class ImportController extends Controller
{
    use TransactionFilter;
    /** @var ImportJobRepositoryInterface Import job repository. */
    private $repository;

    /**
     * ImportController constructor.
     *
     * @codeCoverageIgnore
     */
    public function __construct()
    {
        parent::__construct();
        $this->middleware(
            function ($request, $next) {
                /** @var User $user */
                $user             = auth()->user();
                $this->repository = app(ImportJobRepositoryInterface::class);
                $this->repository->setUser($user);

                return $next($request);
            }
        );
    }

    /**
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function listAll(): JsonResponse
    {
        // create some objects:
        $manager  = $this->getManager();
        $pageSize = (int)app('preferences')->getForUser(auth()->user(), 'listPageSize', 50)->data;

        // get list of accounts. Count it and split it.
        $collection = $this->repository->get();
        $count      = $collection->count();
        $importJobs = $collection->slice(($this->parameters->get('page') - 1) * $pageSize, $pageSize);

        // make paginator:
        $paginator = new LengthAwarePaginator($importJobs, $count, $pageSize, $this->parameters->get('page'));
        $paginator->setPath(route('api.v1.import.list') . $this->buildParams());

        /** @var ImportJobTransformer $transformer */
        $transformer = app(ImportJobTransformer::class);
        $transformer->setParameters($this->parameters);

        $resource = new FractalCollection($importJobs, $transformer, 'import_jobs');
        $resource->setPaginator(new IlluminatePaginatorAdapter($paginator));

        return response()->json($manager->createData($resource)->toArray())->header('Content-Type', 'application/vnd.api+json');
    }

    /**
     * @param ImportJob $importJob
     *
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function show(ImportJob $importJob): JsonResponse
    {
        $manager = $this->getManager();
        /** @var ImportJobTransformer $transformer */
        $transformer = app(ImportJobTransformer::class);
        $transformer->setParameters($this->parameters);

        $resource = new Item($importJob, $transformer, 'import_jobs');

        return response()->json($manager->createData($resource)->toArray())->header('Content-Type', 'application/vnd.api+json');
    }

    /**
     * Show all transactions
     *
     * @param Request   $request
     * @param ImportJob $importJob
     *
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function transactions(Request $request, ImportJob $importJob): JsonResponse
    {
        $pageSize = (int)app('preferences')->getForUser(auth()->user(), 'listPageSize', 50)->data;
        $type     = $request->get('type') ?? 'default';
        $this->parameters->set('type', $type);

        $types   = $this->mapTransactionTypes($this->parameters->get('type'));
        $manager = $this->getManager();

        $tag          = $importJob->tag;
        $transactions = new Collection();
        $paginator    = new LengthAwarePaginator($transactions, 0, $pageSize);
        $paginator->setPath(route('api.v1.import.transactions', [$importJob->key]) . $this->buildParams());

        if (null !== $tag) {
            /** @var User $admin */
            $admin = auth()->user();

            // use new group collector:
            /** @var GroupCollectorInterface $collector */
            $collector = app(GroupCollectorInterface::class);
            $collector
                ->setUser($admin)
                // filter on tag.
                ->setTag($tag)
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
        }

        /** @var TransactionGroupTransformer $transformer */
        $transformer = app(TransactionGroupTransformer::class);
        $transformer->setParameters($this->parameters);

        $resource = new FractalCollection($transactions, $transformer, 'transactions');
        $resource->setPaginator(new IlluminatePaginatorAdapter($paginator));

        return response()->json($manager->createData($resource)->toArray())->header('Content-Type', 'application/vnd.api+json');
    }

}

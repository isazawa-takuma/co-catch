<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActivitySaveRequest;
use App\Http\Requests\CustomerBulkOwnerUpdateRequest;
use App\Http\Requests\CustomerImportRequest;
use App\Http\Requests\CustomerUpdateRequest;
use App\Http\Requests\UserCustomerUpdateRequest;
use App\Models\Activity;
use App\Models\Customer;
use App\Services\Opnavi\CustomerActivityService;
use App\Services\Opnavi\CustomerImportService;
use App\Services\Opnavi\CustomerQueryService;
use App\Services\Opnavi\CustomerService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    public function __construct(
        private CustomerActivityService $activityService,
        private CustomerImportService $importService,
        private CustomerQueryService $queryService,
        private CustomerService $customerService
    ) {
    }

    public function index(Request $request)
    {
        return $this->listView($request);
    }

    public function userIndex(Request $request)
    {
        return $this->listView($request);
    }

    private function listView(Request $request)
    {
        $filters = array_merge($request->all(), [
            'sort_by' => $this->queryService->sortBy($request),
            'sort_order' => $this->queryService->sortOrder($request),
        ]);

        return view('customers.index', [
            'customers' => $this->queryService->paginate($request),
            'users' => $this->queryService->activeUsers(),
            'statuses' => Customer::STATUSES,
            'filters' => $filters,
            'regions' => $this->queryService->regions(),
        ]);
    }

    public function show(Request $request, Customer $customer)
    {
        return $this->detailView($request, $customer);
    }

    public function userShow(Request $request, Customer $customer)
    {
        return $this->detailView($request, $customer);
    }

    private function detailView(Request $request, Customer $customer)
    {
        $customer->load(['owner', 'otaLinks', 'activities.user']);

        $view = $request->boolean('modal') && $request->ajax() ? 'customers._detail' : 'customers.show';

        return view($view, [
            'customer' => $customer,
            'users' => $this->queryService->activeUsers(),
            'statuses' => Customer::STATUSES,
            'contactStatuses' => Activity::CONTACT_STATUSES,
            'previousCustomer' => $this->queryService->previousCustomer($customer, $request),
            'nextCustomer' => $this->queryService->nextCustomer($customer, $request),
        ]);
    }

    public function update(CustomerUpdateRequest $request, Customer $customer)
    {
        $data = $request->validated();
        unset($data['redirect_to']);

        $this->customerService->update($customer, $data);

        return $this->redirectToCustomerDetail($request, $customer)->with('status', '保存しました');
    }

    public function userUpdate(UserCustomerUpdateRequest $request, Customer $customer)
    {
        $data = $request->safe()->only([
            'contact_phone',
            'next_action_at',
            'sales_memo',
        ]);

        $this->customerService->update($customer, $data);

        return $this->redirectToCustomerDetail($request, $customer, 'user')->with('status', '保存しました');
    }

    public function bulkUpdateOwner(CustomerBulkOwnerUpdateRequest $request)
    {
        $data = $request->validated();
        $updatedCount = $this->customerService->bulkUpdateOwner($data['customer_ids'], $data['owner_id'] ?? null);
        $ownerName = $this->queryService->activeUsers()->firstWhere('id', (int) ($data['owner_id'] ?? 0))?->name ?? '未担当';

        return $this->redirectToList($request)
            ->with('status', $updatedCount.'件の担当者を'.$ownerName.'に更新しました');
    }

    public function storeActivity(ActivitySaveRequest $request, Customer $customer)
    {
        $this->activityService->create($customer, $request->validated());

        return $this->redirectToCustomerDetail($request, $customer)
            ->with('status', '履歴を登録しました')
            ->with('status_area', 'activity');
    }

    public function userStoreActivity(ActivitySaveRequest $request, Customer $customer)
    {
        $this->activityService->create($customer, $request->validated());

        return $this->redirectToCustomerDetail($request, $customer, 'user')
            ->with('status', '履歴を登録しました')
            ->with('status_area', 'activity');
    }

    public function updateActivity(ActivitySaveRequest $request, Customer $customer, Activity $activity)
    {
        $this->activityService->update($customer, $activity, $request->validated());

        return $this->redirectToCustomerDetail($request, $customer)
            ->with('status', '履歴を更新しました')
            ->with('status_area', 'activity');
    }

    public function userUpdateActivity(ActivitySaveRequest $request, Customer $customer, Activity $activity)
    {
        $this->activityService->update($customer, $activity, $request->validated());

        return $this->redirectToCustomerDetail($request, $customer, 'user')
            ->with('status', '履歴を更新しました')
            ->with('status_area', 'activity');
    }

    public function destroyActivity(Request $request, Customer $customer, Activity $activity)
    {
        $this->activityService->delete($customer, $activity);

        return $this->redirectToCustomerDetail($request, $customer)
            ->with('status', '履歴を削除しました')
            ->with('status_area', 'activity');
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();

        return redirect()->route('customers.index')->with('status', '顧客を削除しました');
    }

    public function import(CustomerImportRequest $request)
    {
        $file = $request->file('csv_file');
        $result = $this->importService->import(
            $file->getRealPath(),
            $file->getClientOriginalName(),
            $request->boolean('confirm_duplicates')
        );

        if (! empty($result['errors'])) {
            return back()->with('import_errors', $result['errors'])->with('open_import', true);
        }

        return redirect()
            ->route('customers.index')
            ->with('status', $result['message'])
            ->with('import_warnings', $result['warnings']);
    }

    private function redirectToCustomerDetail(Request $request, Customer $customer, string $screen = 'admin')
    {
        if ($request->filled('redirect_to') && Str::startsWith($request->input('redirect_to'), url('/'))) {
            return redirect()->to($request->input('redirect_to'));
        }

        $showRoute = $screen === 'user' ? 'user.customers.show' : 'customers.show';

        if ($request->boolean('modal')) {
            return redirect()->route($showRoute, ['customer' => $customer, 'modal' => 1]);
        }

        return redirect()->route($showRoute, $customer);
    }

    private function redirectToList(Request $request)
    {
        if ($request->filled('redirect_to') && Str::startsWith($request->input('redirect_to'), url('/'))) {
            return redirect()->to($request->input('redirect_to'));
        }

        return redirect()->route('customers.index');
    }
}

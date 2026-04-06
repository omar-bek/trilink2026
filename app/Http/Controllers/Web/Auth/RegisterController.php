<?php

namespace App\Http\Controllers\Web\Auth;

use App\Enums\CompanyType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\CompanyRegisteredNotification;
use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    public function showForm(): View
    {
        $countries = [
            'UAE' => 'United Arab Emirates',
            'SA'  => 'Saudi Arabia',
            'KW'  => 'Kuwait',
            'QA'  => 'Qatar',
            'BH'  => 'Bahrain',
            'OM'  => 'Oman',
            'EG'  => 'Egypt',
            'JO'  => 'Jordan',
        ];

        // Companies pick what they do at registration time. Only the
        // operational types are exposed — government/admin are platform
        // roles assigned manually, never self-registered.
        $companyTypes = [
            CompanyType::BUYER->value            => __('register.type_buyer'),
            CompanyType::SUPPLIER->value         => __('register.type_supplier'),
            CompanyType::LOGISTICS->value        => __('register.type_logistics'),
            CompanyType::CLEARANCE->value        => __('register.type_clearance'),
            CompanyType::SERVICE_PROVIDER->value => __('register.type_service_provider'),
        ];

        return view('auth.register', compact('countries', 'companyTypes'));
    }

    public function showSuccess(): View
    {
        return view('auth.registration-success');
    }

    public function register(Request $request): RedirectResponse
    {
        // Self-registration only: never let the form pick admin/government.
        $allowedTypes = [
            CompanyType::BUYER->value,
            CompanyType::SUPPLIER->value,
            CompanyType::LOGISTICS->value,
            CompanyType::CLEARANCE->value,
            CompanyType::SERVICE_PROVIDER->value,
        ];

        // Be friendly about the website field: users routinely type
        // "company.com" without a protocol. Prepend https:// so the
        // built-in `url` rule accepts it.
        if ($request->filled('website') && !preg_match('~^https?://~i', (string) $request->input('website'))) {
            $request->merge(['website' => 'https://' . trim((string) $request->input('website'))]);
        }

        $data = $request->validate([
            'company_name_en' => ['required', 'string', 'max:255'],
            'company_name_ar' => ['nullable', 'string', 'max:255'],
            'company_type'    => ['required', 'string', \Illuminate\Validation\Rule::in($allowedTypes)],
            'trade_license'   => ['required', 'string', 'max:100', 'unique:companies,registration_number'],
            'tax_number'      => ['nullable', 'string', 'max:100'],
            'country'         => ['required', 'string', 'max:10'],
            'city'            => ['required', 'string', 'max:100'],
            'address'         => ['required', 'string', 'max:500'],
            'phone'           => ['required', 'string', 'max:30'],
            'email'           => ['required', 'email', 'max:255'],
            'website'         => ['nullable', 'url', 'max:255'],
            'description'     => ['nullable', 'string', 'max:2000'],

            'trade_license_file'    => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'tax_certificate_file'  => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'company_profile_file'  => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],

            'manager_name'     => ['required', 'string', 'max:255'],
            'manager_email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'manager_phone'    => ['required', 'string', 'max:30'],
            'manager_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // Split manager name into first/last (best effort).
        $parts     = preg_split('/\s+/', trim($data['manager_name']), 2);
        $firstName = $parts[0] ?? $data['manager_name'];
        $lastName  = $parts[1] ?? '';

        // Persist uploaded documents (if any) to the company's documents folder.
        $documents = [];
        foreach (['trade_license_file', 'tax_certificate_file', 'company_profile_file'] as $field) {
            if ($file = $request->file($field)) {
                $documents[$field] = $file->store('companies/documents', 'public');
            }
        }

        $result = $this->authService->registerCompany([
            'company_name'        => $data['company_name_en'],
            'company_name_ar'     => $data['company_name_ar'] ?? null,
            'registration_number' => $data['trade_license'],
            'tax_number'          => $data['tax_number'] ?? null,
            'company_type'        => CompanyType::from($data['company_type']),
            'company_email'       => $data['email'],
            'company_phone'       => $data['phone'],
            'website'             => $data['website'] ?? null,
            'address'             => $data['address'],
            'city'                => $data['city'],
            'country'             => $data['country'],
            'description'         => $data['description'] ?? null,

            'first_name' => $firstName,
            'last_name'  => $lastName,
            'email'      => $data['manager_email'],
            'password'   => $data['manager_password'],
            'phone'      => $data['manager_phone'],
        ]);

        // Attach uploaded documents to the company record.
        if (!empty($documents)) {
            $result['user']->company?->update(['documents' => $documents]);
        }

        // Auto-login the brand-new manager so they land on the success page
        // already authenticated. The EnsureCompanyApproved middleware will
        // keep them out of the dashboard until an admin approves the
        // company — they can only see the "pending" page.
        Auth::login($result['user']);

        // Defer the admin notification until AFTER the HTTP response has
        // been sent to the user. With a sync mailer (MAIL_MAILER=log/smtp)
        // sending inline can take long enough that the browser appears to
        // hang on the submit, the redirect is delayed, and the user thinks
        // nothing happened. Running it in `terminating()` makes the user's
        // redirect instant — the email goes out a beat later in the same
        // PHP process, after the response has flushed.
        $companyId = $result['user']->company->id;
        app()->terminating(function () use ($companyId) {
            try {
                $company = \App\Models\Company::find($companyId);
                if (!$company) {
                    return;
                }
                $admins = User::where('role', UserRole::ADMIN->value)->get();
                if ($admins->isNotEmpty()) {
                    Notification::send($admins, new CompanyRegisteredNotification($company));
                }
            } catch (\Throwable $e) {
                report($e);
            }
        });

        return redirect()
            ->route('register.success')
            ->with('registered_email', $data['manager_email']);
    }
}

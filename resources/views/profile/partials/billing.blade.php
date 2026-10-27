@php
    $cardClass = 'border border-[#d8dce2] bg-white p-4';
    $primaryButtonClass = 'inline-flex items-center rounded-surface border border-[#933c1f] bg-[#933c1f] px-3 py-2 text-sm font-semibold text-white transition hover:bg-[#7f3219] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]';
    $secondaryButtonClass = 'inline-flex items-center rounded-surface border border-[#d8dce2] bg-white px-3 py-2 text-sm font-semibold text-[#0f2133] transition hover:border-[#c5cbd3] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]';
    $dangerButtonClass = 'inline-flex items-center rounded-surface border border-[#bf1f2f] bg-[#dc3545] px-3 py-2 text-sm font-semibold text-white transition hover:bg-[#bf1f2f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#bf1f2f]';
    $metaClass = 'text-sm text-[#5c6570]';
@endphp

<div class="grid gap-5">
    @if(session('billing_status'))
        <div class="rounded-surface border border-[#b9d6bd] bg-[#f1fbf2] px-3 py-2 text-sm text-[#1f5130]" role="status" aria-live="polite">
            {{ session('billing_status') }}
        </div>
    @endif

    <div>
        <h2 class="text-lg font-semibold text-[#0f2133]">Memberships & Billing</h2>
        <p class="mt-1 {{ $metaClass }}">Choose a free or paid membership, track your current access, and manage renewals.</p>
    </div>

    <div class="grid gap-5 lg:grid-cols-[1.4fr_0.9fr]">
        <section class="grid gap-4 md:grid-cols-2" aria-label="Membership plans">
            @forelse($plans as $plan)
                @php
                    $planMembership = $memberships->first(fn ($membership) => (int) $membership->membership_plan_id === (int) $plan->id);
                    $activeMembership = $planMembership?->isCurrentlyActive() ? $planMembership : null;
                    $pendingMembership = ($planMembership?->status?->value ?? $planMembership?->status) === 'pending' ? $planMembership : null;
                @endphp
                <article class="{{ $cardClass }}">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-[#0f2133]">{{ $plan->name }}</h3>
                            <p class="mt-1 {{ $metaClass }}">{{ $plan->description ?: 'Membership access for the forum and related premium areas.' }}</p>
                        </div>
                        <span class="rounded-pill border border-[#d8dce2] bg-white px-2.5 py-1 text-xs font-semibold uppercase tracking-[0.05em] text-[#5c6570]">
                            {{ $plan->is_free ? 'Free' : ($plan->billingIntervalLabel() ?? 'Paid') }}
                        </span>
                    </div>

                    @if(!$plan->is_free && filled($plan->provider))
                        <p class="mt-3 {{ $metaClass }}">Provider: {{ ucfirst((string) $plan->provider) }}</p>
                    @endif

                    @php
                        $syncedPrices = $plan->is_free ? collect() : $plan->prices->filter(fn ($price) => $price->isSyncedWithProvider());
                    @endphp
                    @if($syncedPrices->isNotEmpty())
                        <p class="mt-1 {{ $metaClass }}">
                            {{ $syncedPrices->count() > 1 ? 'Prices' : 'Price' }}:
                            {{ $syncedPrices->map(fn ($price) => $price->formattedAmount())->implode(' / ') }}
                        </p>
                    @endif

                    @if($plan->featureList() !== [])
                        <ul class="mt-4 grid gap-2 text-sm text-[#0f2133]">
                            @foreach($plan->featureList() as $feature)
                                <li>- {{ $feature }}</li>
                            @endforeach
                        </ul>
                    @endif

                    @if($plan->entitlementList() !== [])
                        <p class="mt-4 {{ $metaClass }}">Entitlements: {{ implode(', ', $plan->entitlementList()) }}</p>
                    @endif

                    <div class="mt-5 flex flex-wrap gap-2">
                        @if($activeMembership)
                            <span class="{{ $secondaryButtonClass }}">Active</span>
                        @elseif($pendingMembership)
                            <span class="{{ $secondaryButtonClass }}">Pending Confirmation</span>
                        @elseif(!$plan->isCheckoutReady())
                            <span class="{{ $secondaryButtonClass }}">Billing Not Configured</span>
                        @else
                            <form method="post" action="{{ route('billing.subscribe', $plan) }}" class="grid gap-2">
                                @csrf
                                @if(!$plan->is_free && $syncedPrices->count() > 1)
                                    <label class="grid gap-1 text-sm">
                                        <span class="{{ $metaClass }}">Currency</span>
                                        <select name="currency" class="rounded-surface border border-[#d8dce2] bg-white px-3 py-2 text-sm text-[#0f2133]">
                                            @foreach($syncedPrices as $price)
                                                <option value="{{ $price->currency }}" @selected($price->is_default)>{{ $price->formattedAmount() }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                @elseif(!$plan->is_free && $syncedPrices->count() === 1)
                                    <input type="hidden" name="currency" value="{{ $syncedPrices->first()->currency }}">
                                @endif
                                <button type="submit" class="{{ $primaryButtonClass }}">
                                    {{ $plan->is_free ? 'Join Free Membership' : ($plan->provider === 'paypal' ? 'Subscribe with PayPal' : 'Subscribe') }}
                                </button>
                            </form>
                        @endif
                    </div>
                </article>
            @empty
                <p class="{{ $metaClass }}">No membership plans have been published yet.</p>
            @endforelse
        </section>

        <aside class="grid gap-4">
            <section class="{{ $cardClass }}">
                <h3 class="text-lg font-semibold text-[#0f2133]">Current Access</h3>
                @if($memberships->isEmpty())
                    <p class="mt-2 {{ $metaClass }}">You do not have any membership records yet.</p>
                @else
                    <div class="mt-3 grid gap-3">
                        @foreach($memberships as $membership)
                            <article class="border border-[#d8dce2] bg-white p-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <h4 class="text-sm font-semibold text-[#0f2133]">{{ $membership->plan?->name ?? 'Unknown plan' }}</h4>
                                        <p class="mt-1 {{ $metaClass }}">Status: {{ str_replace('_', ' ', $membership->status?->value ?? (string) $membership->status) }}</p>
                                        <p class="{{ $metaClass }}">Provider: {{ ucfirst((string) $membership->provider) }}</p>
                                        @if($membership->current_period_end)
                                            <p class="{{ $metaClass }}">Renews / ends: {{ \App\Support\LocalTime::format($membership->current_period_end) }}</p>
                                        @endif
                                    </div>
                                    @if(($membership->status?->value ?? $membership->status) === 'canceling')
                                        <span class="{{ $secondaryButtonClass }}">Cancels at period end</span>
                                    @elseif($membership->isCurrentlyActive())
                                        <form method="post" action="{{ route('billing.cancel', $membership) }}">
                                            @csrf
                                            <button type="submit" class="{{ $dangerButtonClass }}">Cancel</button>
                                        </form>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="{{ $cardClass }}">
                <h3 class="text-lg font-semibold text-[#0f2133]">Resolved Entitlements</h3>
                @if(($entitlements ?? []) === [])
                    <p class="mt-2 {{ $metaClass }}">No scoped entitlements are active on your account.</p>
                @else
                    <ul class="mt-3 grid gap-2 text-sm text-[#0f2133]">
                        @foreach($entitlements as $entitlement)
                            <li>- {{ $entitlement }}</li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </aside>
    </div>
</div>

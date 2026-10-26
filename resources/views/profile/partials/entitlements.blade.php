<div class="grid gap-4">
    <div class="border border-[#d8dce2] bg-white p-3">
        <h2 class="text-lg font-semibold text-[#0f2133]">Current Effective Entitlements</h2>
        <p class="mt-1 {{ $metaClass }}">These are the capabilities currently available to your account.</p>

        @if(!empty($effectiveEntitlements))
            <div class="mt-3 grid gap-3">
                @foreach($effectiveEntitlements as $entitlement)
                    @php($sources = $entitlementExplanations[$entitlement] ?? [])
                    <article class="border border-[#d8dce2] bg-white p-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-pill border border-[#cabca8] bg-[#f8f6f1] px-2.5 py-1 text-xs font-semibold text-[#0f2133]">
                                {{ $entitlement }}
                            </span>

                            @if(count($sources) === 1)
                                <span class="text-sm text-[#5c6570]">
                                    {{ $sources[0]['source_label'] ?? 'Grant source' }}
                                    @if(filled($sources[0]['source_summary'] ?? null))
                                        • {{ $sources[0]['source_summary'] }}
                                    @endif
                                    @if(filled($sources[0]['expires_at'] ?? null))
                                        • Expires {{ \App\Support\LocalTime::format(\Illuminate\Support\Carbon::parse($sources[0]['expires_at'])) }}
                                    @endif
                                </span>
                            @endif
                        </div>

                        @if(count($sources) === 1 && filled($sources[0]['reason'] ?? null))
                            <p class="mt-1 text-sm text-[#0f2133] whitespace-pre-wrap">{{ $sources[0]['reason'] }}</p>
                        @endif

                        @if(count($sources) > 1)
                            <details class="mt-2">
                                <summary class="cursor-pointer text-sm font-medium text-[#0f2133] select-none">
                                    {{ count($sources) }} grant sources
                                </summary>
                                <div class="mt-2 grid gap-2">
                                    @foreach($sources as $explanation)
                                        <div class="border border-[#d8dce2] bg-white px-3 py-2">
                                            <p class="text-sm font-medium text-[#0f2133]">
                                                {{ $explanation['source_label'] ?? 'Grant source' }}
                                                @if(filled($explanation['source_summary'] ?? null))
                                                    <span class="font-normal text-[#5c6570]">• {{ $explanation['source_summary'] }}</span>
                                                @endif
                                            </p>

                                            <p class="mt-1 text-xs text-[#5c6570]">
                                                {{ $explanation['source_reference'] ?? 'Grant source' }}
                                                @if(filled($explanation['expires_at'] ?? null))
                                                    • Expires: {{ \App\Support\LocalTime::format(\Illuminate\Support\Carbon::parse($explanation['expires_at'])) }}
                                                @endif
                                            </p>

                                            @if(filled($explanation['reason'] ?? null))
                                                <p class="mt-1 text-sm text-[#0f2133] whitespace-pre-wrap">{{ $explanation['reason'] }}</p>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </details>
                        @endif
                    </article>
                @endforeach
            </div>
        @else
            <p class="mt-2 {{ $metaClass }}">No effective entitlements are currently assigned.</p>
        @endif
    </div>

    <div>
        <h2 class="text-lg font-semibold text-[#0f2133]">Request Additional Entitlements</h2>
        <p class="mt-1 {{ $metaClass }}">Use this form to request access that is not currently granted through membership or direct assignment.</p>
    </div>

    @if(($requestableEntitlementDefinitions ?? collect())->isNotEmpty())
        <form method="post" action="{{ route('profile.entitlement-requests.store') }}" class="{{ $formClass }}">
            @csrf
            <label class="{{ $fieldClass }}">
                <span class="{{ $labelClass }}">Entitlement</span>
                <select
                    class="{{ $inputClass }}"
                    name="entitlement_key"
                    required
                    @if($entitlementRequestBag->has('entitlement_key')) aria-invalid="true" aria-describedby="profile-entitlement-key-error" @endif
                >
                    <option value="">Select an entitlement</option>
                    @foreach($requestableEntitlementDefinitions as $definition)
                        <option value="{{ $definition->key }}" @selected(old('entitlement_key') === $definition->key)>
                            {{ $definition->label }} ({{ $definition->key }})
                        </option>
                    @endforeach
                </select>
            </label>
            @if($entitlementRequestBag->has('entitlement_key'))
                <p id="profile-entitlement-key-error" class="{{ $errorClass }}">{{ $entitlementRequestBag->first('entitlement_key') }}</p>
            @endif

            <label class="{{ $fieldClass }}">
                <span class="{{ $labelClass }}">Reason (optional)</span>
                <textarea
                    class="{{ $inputClass }}"
                    name="request_reason"
                    rows="4"
                    maxlength="5000"
                    @if($entitlementRequestBag->has('request_reason')) aria-invalid="true" aria-describedby="profile-entitlement-reason-error" @endif
                >{{ old('request_reason') }}</textarea>
            </label>
            @if($entitlementRequestBag->has('request_reason'))
                <p id="profile-entitlement-reason-error" class="{{ $errorClass }}">{{ $entitlementRequestBag->first('request_reason') }}</p>
            @endif

            <button type="submit" class="{{ $primaryButtonClass }}">Submit Entitlement Request</button>
        </form>
    @else
        <p class="{{ $metaClass }}">No requestable entitlements are currently available.</p>
    @endif

    <div>
        <h3 class="text-base font-semibold text-[#0f2133]">Request History</h3>

        @if(($entitlementRequests ?? null) && $entitlementRequests->isNotEmpty())
            <div class="mt-2 grid gap-2.5">
                @foreach($entitlementRequests as $entitlementRequest)
                    <article class="border border-[#d8dce2] bg-white p-3">
                        <div class="flex flex-wrap items-center gap-2 text-sm">
                            <strong>{{ $entitlementRequest->entitlement_key }}</strong>
                            <span class="rounded-pill border border-[#cabca8] px-2 py-0.5 text-xs">
                                {{ $entitlementRequest->status?->label() ?? (string) $entitlementRequest->status }}
                            </span>
                        </div>

                        <p class="mt-1 {{ $metaClass }}">
                            Submitted: {{ $entitlementRequest->requested_at ? \App\Support\LocalTime::format($entitlementRequest->requested_at) : '-' }}
                            @if($entitlementRequest->reviewed_at)
                                • Reviewed: {{ \App\Support\LocalTime::format($entitlementRequest->reviewed_at) }}
                            @endif
                            @if($entitlementRequest->expires_at)
                                • Expires: {{ \App\Support\LocalTime::format($entitlementRequest->expires_at) }}
                            @endif
                        </p>

                        @if(is_string($entitlementRequest->request_reason) && trim($entitlementRequest->request_reason) !== '')
                            <p class="mt-2 text-sm text-[#0f2133] whitespace-pre-wrap">{{ $entitlementRequest->request_reason }}</p>
                        @endif

                        @if(is_string($entitlementRequest->review_notes) && trim($entitlementRequest->review_notes) !== '')
                            <div class="mt-2 border border-[#d8dce2] bg-white p-2.5">
                                <p class="text-xs font-semibold uppercase tracking-[0.04em] text-[#5c6570]">Staff notes</p>
                                <p class="mt-1 text-sm text-[#0f2133] whitespace-pre-wrap">{{ $entitlementRequest->review_notes }}</p>
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>

            @if($entitlementRequests->hasPages())
                @include('profile.partials.subscription_pagination', [
                    'paginator' => $entitlementRequests,
                    'ariaLabel' => 'Entitlement requests pagination',
                ])
            @endif
        @else
            <p class="mt-2 {{ $metaClass }}">No entitlement requests submitted yet.</p>
        @endif
    </div>
</div>

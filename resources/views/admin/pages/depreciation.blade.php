@extends('admin.pages.layout')
@php $currency = (string) \App\Support\AppSettingBag::get('currency_symbol', 'ر.س'); @endphp
@section('content')
<div class="admin-page-header">
    <div>
        <p class="eyebrow">الإهلاك</p>
        <h1>مذكرات الإهلاك</h1>
    </div>
    <div class="page-actions">
        <button type="button" class="primary-button" data-modal-open="modal-depreciation-create">+ إنشاء مذكرة إهلاك</button>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<form class="page-filter-bar" method="GET" action="{{ route('admin.depreciation') }}">
    <label>
        السنة
        <input type="number" name="year" value="{{ request('year') }}" placeholder="2025" min="2020" max="2099">
    </label>
    <label>
        الشهر
        <select name="month">
            <option value="">الكل</option>
            @foreach(range(1,12) as $m)
                <option value="{{ $m }}" @selected((int)request('month') === $m)>{{ $m }}</option>
            @endforeach
        </select>
    </label>
    <label>
        الصندوق
        <input name="fund" value="{{ request('fund') }}" placeholder="depreciation_fund...">
    </label>
    <div class="filter-actions">
        <button type="submit" class="primary-button">تطبيق</button>
        <a href="{{ route('admin.depreciation') }}" class="secondary-button">إعادة تعيين</a>
    </div>
</form>

@if ($items->isEmpty())
    <div class="empty-state panel"><span>⌇</span><p>لا توجد مذكرات إهلاك</p></div>
@else
    <div class="panel table-panel compact-panel">
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>الفترة</th>
                        <th>المبلغ</th>
                        <th>النسبة</th>
                        <th>التاريخ</th>
                        <th>الوصف</th>
                        <th>الصندوق</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        <tr>
                            <td><strong>{{ $item['period'] }}</strong></td>
                            <td class="numeric">{{ $item['amount'] }} {{ $currency }}</td>
                            <td>{{ $item['rate'] }}</td>
                            <td class="muted">{{ $item['date'] }}</td>
                            <td>{{ $item['description'] ?: '—' }}</td>
                            <td>{{ $item['fund'] }}</td>
                            <td>
                                <div class="row-actions">
                                    <button type="button" class="action-edit" data-fill-modal="modal-depreciation-edit"
                                        data-action-url="{{ route('admin.depreciation.update', $item['id']) }}"
                                        data-edit='@json($item["edit_payload"])'>تعديل</button>
                                    <button type="button" class="action-danger" data-post data-method="DELETE"
                                        data-url="{{ route('admin.depreciation.destroy', $item['id']) }}"
                                        data-confirm="سيتم حذف مذكرة الإهلاك «{{ $item['period'] }}» بمبلغ {{ $item['amount'] }} {{ $currency }}. متابعة؟">حذف</button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="pagination-wrap">{{ $items->links() }}</div>
    </div>
@endif

{{-- ── إنشاء مذكرة إهلاك ── --}}
<div class="modal-backdrop" id="modal-depreciation-create" hidden>
    <div class="modal-card">
        <div class="modal-header">
            <h3>+ إنشاء مذكرة إهلاك</h3>
            <button type="button" class="modal-close" data-modal-close aria-label="إغلاق">×</button>
        </div>
        <form data-ajax-form action="{{ route('admin.depreciation.store') }}" method="POST" novalidate>
            @csrf
            <div class="modal-body">
                <div class="modal-grid">
                    <div class="om-field">
                        <label for="dep_amount">المبلغ ({{ $currency }})</label>
                        <input id="dep_amount" name="amount" type="number" required min="0" step="0.01" dir="ltr" placeholder="0.00">
                    </div>
                    <div class="om-field">
                        <label for="dep_rate">النسبة %</label>
                        <input id="dep_rate" name="rate" type="number" required min="0" max="100" step="0.01" dir="ltr" placeholder="10">
                    </div>
                    <div class="om-field">
                        <label for="dep_date">التاريخ</label>
                        <input id="dep_date" name="transaction_date" type="date" required value="{{ now()->toDateString() }}">
                    </div>
                    <div class="om-field">
                        <label for="dep_year">السنة</label>
                        <input id="dep_year" name="year" type="number" required min="2000" max="2100" value="{{ now()->year }}">
                    </div>
                    <div class="om-field">
                        <label for="dep_month">الشهر</label>
                        <select id="dep_month" name="month" required>
                            @foreach (range(1, 12) as $m)
                                <option value="{{ $m }}" @selected($m === (int) now()->format('n'))>{{ $m }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="om-field">
                        <label for="dep_fund">الصندوق</label>
                        <select id="dep_fund" name="fund_id">
                            <option value="">الصندوق الافتراضي (الإهلاك)</option>
                            @foreach ($funds as $fund)
                                <option value="{{ $fund['id'] }}">{{ $fund['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="om-field full">
                        <label for="dep_participant">المشارك (اختياري)</label>
                        <div class="searchable-select" data-searchable>
                            <input type="text" class="searchable-input" placeholder="ابحث عن اسم المشارك..." autocomplete="off">
                            <select id="dep_participant" name="participant_id" class="js-searchable-select" hidden>
                                <option value="">— لا أحد —</option>
                                @foreach ($participants as $participant)
                                    <option value="{{ $participant['id'] }}">{{ $participant['name'] }}</option>
                                @endforeach
                            </select>
                            <ul class="searchable-list"></ul>
                        </div>
                    </div>
                    <div class="om-field full">
                        <label for="dep_description">الوصف</label>
                        <textarea id="dep_description" name="description" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="secondary-button" data-modal-close>إلغاء</button>
                <button type="submit" class="primary-button" data-submit>إنشاء المذكرة</button>
            </div>
        </form>
    </div>
</div>

{{-- ── تعديل مذكرة إهلاك ── --}}
<div class="modal-backdrop" id="modal-depreciation-edit" hidden>
    <div class="modal-card">
        <div class="modal-header">
            <h3>تعديل مذكرة الإهلاك</h3>
            <button type="button" class="modal-close" data-modal-close aria-label="إغلاق">×</button>
        </div>
        <form data-ajax-form action="" method="POST" novalidate>
            @csrf
            @method('PATCH')
            <div class="modal-body">
                <div class="modal-grid">
                    <div class="om-field">
                        <label for="dep_edit_amount">المبلغ ({{ $currency }})</label>
                        <input id="dep_edit_amount" name="amount" type="number" min="0" step="0.01" dir="ltr" placeholder="0.00">
                    </div>
                    <div class="om-field">
                        <label for="dep_edit_rate">النسبة %</label>
                        <input id="dep_edit_rate" name="rate" type="number" min="0" max="100" step="0.01" dir="ltr" placeholder="10">
                    </div>
                    <div class="om-field">
                        <label for="dep_edit_date">التاريخ</label>
                        <input id="dep_edit_date" name="transaction_date" type="date">
                    </div>
                    <div class="om-field">
                        <label for="dep_edit_year">السنة</label>
                        <input id="dep_edit_year" name="year" type="number" min="2000" max="2100">
                    </div>
                    <div class="om-field">
                        <label for="dep_edit_month">الشهر</label>
                        <select id="dep_edit_month" name="month">
                            @foreach (range(1, 12) as $m)
                                <option value="{{ $m }}">{{ $m }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="om-field">
                        <label for="dep_edit_fund">الصندوق</label>
                        <select id="dep_edit_fund" name="fund_id">
                            <option value="">الصندوق الافتراضي (الإهلاك)</option>
                            @foreach ($funds as $fund)
                                <option value="{{ $fund['id'] }}">{{ $fund['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="om-field full">
                        <label for="dep_edit_participant">المشارك (اختياري)</label>
                        <div class="searchable-select" data-searchable>
                            <input type="text" class="searchable-input" placeholder="ابحث عن اسم المشارك..." autocomplete="off">
                            <select id="dep_edit_participant" name="participant_id" class="js-searchable-select" hidden>
                                <option value="">— لا أحد —</option>
                                @foreach ($participants as $participant)
                                    <option value="{{ $participant['id'] }}">{{ $participant['name'] }}</option>
                                @endforeach
                            </select>
                            <ul class="searchable-list"></ul>
                        </div>
                    </div>
                    <div class="om-field full">
                        <label for="dep_edit_description">الوصف</label>
                        <textarea id="dep_edit_description" name="description" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="secondary-button" data-modal-close>إلغاء</button>
                <button type="submit" class="primary-button" data-submit>حفظ التعديلات</button>
            </div>
        </form>
    </div>
</div>
@endsection
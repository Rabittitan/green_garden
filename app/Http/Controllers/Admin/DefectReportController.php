<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\DefectReport;
use App\Models\DefectReportItem;
use App\Services\DefectReportService;
use App\Services\ImageStorage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Phiếu báo hàng lỗi: nhân viên nhóm Kho lập (quét chậu lỗi), quản trị viên duyệt / từ chối; báo cáo hàng lỗi theo kỳ.
 */
class DefectReportController extends Controller
{
    /** Kỳ báo cáo: giống trang Thống kê. */
    public const PERIODS = ['7' => ['7 ngày', 7], '30' => ['30 ngày', 30], '90' => ['90 ngày', 90], 'thang' => ['Tháng này', null]];

    public function __construct(private DefectReportService $defects) {}

    public function index(Request $request): View
    {
        $status = array_key_exists((string) $request->query('status'), DefectReport::STATUSES) ? (string) $request->query('status') : null;
        $reports = DefectReport::with(['creator', 'decider'])->withCount('items')
            ->when($status, fn ($q) => $q->where('status', $status))->latest('id')->paginate(20)->withQueryString();

        return view('admin.defect-reports.index', [
            'reports' => $reports, 'status' => $status,
            'counts' => DefectReport::selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    public function create(): View
    {
        return view('admin.defect-reports.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'lines' => ['required', 'array', 'max:200'],
            'lines.*.type' => ['required', Rule::in(array_keys(DefectReportItem::TYPES))],
            'lines.*.note' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
            'photos' => ['nullable', 'array', 'max:'.DefectReportService::MAX_PHOTOS],
            'photos.*' => ImageStorage::RULES,
        ], ['lines.required' => 'Quét tem của ít nhất 1 chậu lỗi.'],
            ['lines' => 'chậu lỗi', 'lines.*.type' => 'loại lỗi', 'note' => 'ghi chú', 'photos' => 'ảnh', 'photos.*' => 'ảnh']);
        try {
            $report = $this->defects->create($request->user(), $data['lines'], $data['note'] ?? null, array_values($request->file('photos', [])));
        } catch (BusinessException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.defect-reports.show', $report)->with('success', "Đã gửi phiếu {$report->code}, chờ quản trị viên duyệt.");
    }

    public function show(DefectReport $defectReport): View
    {
        $defectReport->load(['creator', 'decider', 'items.unit', 'items.plant']);

        return view('admin.defect-reports.show', ['report' => $defectReport]);
    }

    public function approve(Request $request, DefectReport $defectReport): RedirectResponse
    {
        return $this->run(fn () => $this->defects->approve($defectReport, $request->user()), $defectReport,
            'Đã duyệt: các chậu lỗi đã được xuất hủy khỏi kho.');
    }

    public function reject(Request $request, DefectReport $defectReport): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']], [], ['reason' => 'lý do từ chối']);

        return $this->run(fn () => $this->defects->reject($defectReport, $request->user(), $data['reason']), $defectReport, 'Đã từ chối phiếu.');
    }

    /** Báo cáo hàng lỗi theo kỳ (giờ Việt Nam): số chậu + giá trị thiệt hại theo loại lỗi / cây / nhà cung cấp. */
    public function report(Request $request): View
    {
        $key = array_key_exists((string) $request->query('ky'), self::PERIODS) ? (string) $request->query('ky') : '30';
        $tz = (string) config('shop.display_timezone');
        $today = now($tz)->startOfDay();
        $days = self::PERIODS[$key][1] ?? (int) $today->day;
        $from = $today->copy()->subDays($days - 1);

        return view('admin.defect-reports.report', [
            'period' => $key, 'periodLabel' => self::PERIODS[$key][0],
            'summary' => $this->defects->summary($from->copy()->utc(), $today->copy()->addDay()->utc()),
            'pending' => DefectReport::where('status', 'submitted')->count(),
        ]);
    }

    private function run(callable $fn, DefectReport $report, string $ok): RedirectResponse
    {
        try {
            $fn();
        } catch (BusinessException $e) {
            return redirect()->route('admin.defect-reports.show', $report)->with('error', $e->getMessage());
        }

        return redirect()->route('admin.defect-reports.show', $report)->with('success', $ok);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingMenu;
use App\Models\Category;
use App\Models\Menu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
class BookingController extends Controller
{
    // Hiển thị danh sách booking của user
    public function index()
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();
        $bookings = $user->bookings()->latest()->paginate(10);
        
        return view('bookings.index', [
            'bookings' => $bookings
        ]);
    }

    // Hiển thị form tạo booking mới
    public function create()
    {
        $categories = Category::with(['menu' => function($query) {
            $query->where('status', 1)
                  ->orderBy('position');
        }])
        ->where('status', 1)
        ->get();
        
        return view('front.booking', compact('categories'));
    }

    // Lưu booking mới
    public function store(Request $request)
    {
        \Log::info('Booking request data:', $request->all());
        
        try {
            // Validate dữ liệu
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'phone' => [
                    'required',
                    'string',
                    'regex:/^([0-9\s\-\+\(\)]*)$/',
                    'min:10',
                    'max:11'
                ],
                'booking_date' => [
                    'required',
                    'date',
                    'after:' . now()->addHours(0.5)->format('Y-m-d H:i:s')
                ],
                'number_of_people' => 'required|integer|min:1|max:10',
                'special_request' => 'nullable|string|max:500',
                'booking_type' => 'required|in:only_table,with_menu',
                
                // Sửa lại validation cho menu items
                'menu_items' => 'required_if:booking_type,with_menu|array',
                'menu_items.*.selected' => 'required_if:booking_type,with_menu|string|in:on',  // Chấp nhận giá trị "on"
                'menu_items.*.quantity' => 'required_if:menu_items.*.selected,on|integer|min:1|max:10',
            ], [
                // ... các message giữ nguyên
            ]);

            if ($validator->fails()) {
                \Log::warning('Validation failed:', ['errors' => $validator->errors()->toArray()]);
                return redirect()->back()
                    ->withErrors($validator)
                    ->withInput();
            }

            $validated = $validator->validated();
            \Log::info('Validation passed:', $validated);

            // Kiểm tra nếu khách vãng lai và chọn đặt bàn kèm món ăn
            if (!Auth::check() && $validated['booking_type'] === 'with_menu') {
                return redirect()->route('front.booking')
                    ->with('error', 'Bạn cần đăng nhập để đặt bàn kèm món ăn.');
            }

            DB::beginTransaction();
            \Log::info('Validated data:', [
                'booking_type' => $validated['booking_type'],
                'all' => $validated
            ]);
            // Tạo booking
            $booking = Booking::create([
                'user_id' => Auth::id(),
                'name' => $validated['name'],
                'phone' => $validated['phone'],
                'booking_date' => $validated['booking_date'],
                'number_of_people' => $validated['number_of_people'],
                'special_request' => $validated['special_request'] ?? null,
                'status' => 'pending',
                'booking_type' => $validated['booking_type'],
                'total_amount' => 0
            ]);

            \Log::info('Created booking:', $booking->toArray());

            // Nếu đặt bàn kèm món ăn
            if ($validated['booking_type'] === 'with_menu' && !empty($validated['menu_items'])) {
                $totalAmount = 0;
                
                foreach ($validated['menu_items'] as $menuId => $item) {
                    if (isset($item['selected']) && $item['selected'] === 'on') {
                        try {
                            $menu = Menu::findOrFail($menuId);
                            $subtotal = $menu->price * $item['quantity'];

                            $bookingMenu = BookingMenu::create([
                                'booking_id' => $booking->id,
                                'menu_id' => $menuId,
                                'quantity' => $item['quantity'],
                                'price' => $menu->price,
                                'subtotal' => $subtotal
                            ]);
                            \Log::info('Created booking menu item:', $bookingMenu->toArray());

                            $totalAmount += $subtotal;
                        } catch (\Exception $e) {
                            \Log::error('Error creating booking menu:', [
                                'menu_id' => $menuId,
                                'error' => $e->getMessage()
                            ]);
                            throw $e;
                        }
                    }
                }

                $booking->update(['total_amount' => $totalAmount]);
                \Log::info('Updated booking with total amount:', ['booking_id' => $booking->id, 'total' => $totalAmount]);
            }

            // Lưu dữ liệu vào session
            $sessionData = [
                'booking_type' => $booking->booking_type,
                'name' => $booking->name,
                'phone' => $booking->phone,
                'booking_date' => $booking->booking_date,
                'number_of_people' => $booking->number_of_people,
                'special_request' => $booking->special_request,
                'menu_items' => $validated['menu_items'] ?? []
            ];

            session(['booking_data' => $sessionData]);

            DB::commit();

            // Xử lý theo loại đặt bàn và trạng thái đăng nhập
            if (!Auth::check() || $validated['booking_type'] === 'only_table') {
                // Khách vãng lai hoặc user chỉ đặt bàn
                session()->forget('booking_data');
                return redirect()->route('front.booking.success')
                    ->with('success', 'Đặt bàn thành công! Nhân viên của chúng tôi sẽ gọi điện xác nhận trong thời gian sớm nhất.');
            }

            // User đặt bàn kèm món ăn, chuyển đến trang xác nhận để thanh toán
            return redirect()->route('front.confirm');

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Booking error:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect()->back()
                ->with('error', 'Có lỗi xảy ra khi đặt bàn. Vui lòng thử lại.')
                ->withInput();
        }
    }

    // Hủy booking
    public function cancel(Booking $booking)
    {
        if ($booking->user_id !== Auth::id()) {
            return redirect()->route('bookings.index')
                ->with('error', 'Bạn không có quyền hủy đặt bàn này.');
        }

        $booking->update(['status' => 'cancelled']);

        return redirect()->route('bookings.index')
            ->with('success', 'Đặt bàn đã được hủy.');
    }

    public function showConfirmation()
    {
        $bookingData = session('booking_data');
        \Log::info('Showing confirmation page with data:', ['booking_data' => $bookingData]);

        if (!$bookingData) {
            return redirect()->route('front.booking')
                ->with('error', 'Không tìm thấy thông tin đặt bàn');
        }

        // Tìm booking mới nhất của user hiện tại
        $booking = Booking::where('user_id', Auth::id())
                         ->latest()
                         ->first();

        if (!$booking) {
            return redirect()->route('front.booking')
                ->with('error', 'Không tìm thấy thông tin đặt bàn');
        }

        // Tính tổng tiền từ booking
        $totalAmount = $booking->total_amount;

        \Log::info('Confirmation data:', [
            'booking_id' => $booking->id,
            'total_amount' => $totalAmount
        ]);

        // Lưu booking_id vào session
        session(['current_booking_id' => $booking->id]);

        return view('front.confirm', compact('bookingData', 'booking', 'totalAmount'));
    }

    public function processPayment(Request $request)
    {
        \Log::info('Process Payment Started', $request->all());
        
        // Lấy booking_id từ session nếu không có trong request
        $bookingId = $request->booking_id ?? session('current_booking_id');
        
        if (!$bookingId) {
            \Log::error('No booking ID found');
            return redirect()->route('front.booking')
                ->with('error', 'Không tìm thấy thông tin đặt bàn');
        }

        try {
            \Log::info('Finding booking with ID: ' . $bookingId);
            $booking = Booking::findOrFail($bookingId);
            
            // Lấy thông tin cấu hình từ file config
            $vnp_Url = config('vnpay.url');
            $vnp_TmnCode = config('vnpay.tmn_code');
            $vnp_HashSecret = config('vnpay.hash_secret');
            $vnp_ReturnUrl = config('vnpay.return_url');

            \Log::info('VNPay Config', [
                'url' => $vnp_Url,
                'tmn_code' => $vnp_TmnCode,
                'return_url' => $vnp_ReturnUrl
            ]);

            // Tạo mã giao dịch với format: bookingId-timestamp
            $vnp_TxnRef = $booking->id . '-' . time();
            
            // Lưu mã giao dịch vào session
            session(['vnpay_booking_id' => $booking->id]);

            $vnp_Amount = $request->amount * 100;
            
            \Log::info('Payment Details', [
                'booking_id' => $booking->id,
                'amount' => $vnp_Amount,
                'txn_ref' => $vnp_TxnRef
            ]);

            $inputData = array(
                "vnp_Version" => "2.1.0",
                "vnp_TmnCode" => $vnp_TmnCode,
                "vnp_Amount" => $vnp_Amount,
                "vnp_Command" => "pay",
                "vnp_CreateDate" => date('YmdHis'),
                "vnp_CurrCode" => "VND",
                "vnp_IpAddr" => request()->ip(),
                "vnp_Locale" => "vn",
                "vnp_OrderInfo" => "Thanh toan dat ban - " . $vnp_TxnRef,
                "vnp_OrderType" => "billpayment",
                "vnp_ReturnUrl" => $vnp_ReturnUrl,
                "vnp_TxnRef" => $vnp_TxnRef
            );

            ksort($inputData);
            $query = "";
            $i = 0;
            $hashdata = "";
            foreach ($inputData as $key => $value) {
                if ($i == 1) {
                    $hashdata .= '&' . urlencode($key) . "=" . urlencode($value);
                } else {
                    $hashdata .= urlencode($key) . "=" . urlencode($value);
                    $i = 1;
                }
                $query .= urlencode($key) . "=" . urlencode($value) . '&';
            }

            $vnp_Url = $vnp_Url . "?" . $query;
            if (isset($vnp_HashSecret)) {
                $vnpSecureHash = hash_hmac('sha512', $hashdata, $vnp_HashSecret);
                $vnp_Url .= 'vnp_SecureHash=' . $vnpSecureHash;
            }

            \Log::info('Generated VNPay URL', ['url' => $vnp_Url]);

            // Chuyển hướng đến VNPay
            return redirect()->away($vnp_Url);

        } catch (\Exception $e) {
            \Log::error('Payment Processing Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect()->back()
                ->with('error', 'Có lỗi xảy ra khi xử lý thanh toán');
        }
    }

    public function edit(Booking $booking)
    {
        // Kiểm tra quyền sửa
        if (!$booking->can_edit) {
            return redirect()->route('bookings.index')
                ->with('error', 'Không thể sửa đơn đặt bàn này.');
        }

        // Lấy danh sách menu nếu là đặt bàn kèm món
        $menus = [];
        if ($booking->booking_type === 'with_menu') {
            $menus = Menu::active()->get();
        }

        return view('bookings.edit', compact('booking', 'menus'));
    }

    public function update(Request $request, Booking $booking)
    {
        // Kiểm tra quyền sửa
        if (!$booking->can_edit) {
            return redirect()->route('bookings.index')
                ->with('error', 'Không thể sửa đơn đặt bàn này.');
        }

        // Validate và cập nhật tương tự như store()
        try {
            DB::beginTransaction();
            
            // Cập nhật thông tin booking
            $booking->update([
                'name' => $request->name,
                'phone' => $request->phone,
                'booking_date' => $request->booking_date,
                'number_of_people' => $request->number_of_people,
                'special_request' => $request->special_request
            ]);

            // Nếu là đặt bàn kèm món, cập nhật menu items
            if ($booking->booking_type === 'with_menu') {
                // Xóa menu items cũ
                $booking->bookingMenus()->delete();
                
                // Thêm menu items mới
                $totalAmount = 0;
                foreach ($request->menu_items as $menuId => $item) {
                    if (isset($item['selected']) && $item['selected'] === 'on') {
                        $menu = Menu::findOrFail($menuId);
                        $subtotal = $menu->price * $item['quantity'];

                        BookingMenu::create([
                            'booking_id' => $booking->id,
                            'menu_id' => $menuId,
                            'quantity' => $item['quantity'],
                            'price' => $menu->price,
                            'subtotal' => $subtotal
                        ]);

                        $totalAmount += $subtotal;
                    }
                }

                $booking->update(['total_amount' => $totalAmount]);
            }

            DB::commit();

            return redirect()->route('bookings.index')
                ->with('success', 'Cập nhật đơn đặt bàn thành công.');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Có lỗi xảy ra khi cập nhật đơn đặt bàn.')
                ->withInput();
        }
    }

    public function confirm(Request $request)
    {
        // Chỉ xử lý khi user đã đăng nhập
        if (!Auth::check()) {
            return redirect()->route('front.booking');
        }

        $bookingData = session('booking_data');
        if (!$bookingData) {
            return redirect()->route('front.booking')
                ->with('error', 'Không tìm thấy thông tin đặt bàn');
        }

        return view('front.confirm', compact('bookingData'));
    }

    public function success()
    {
        return view('front.booking.success');
    }

    public function vnpayReturn(Request $request)
    {
        // Kiểm tra checksum
        $vnp_SecureHash = $request->vnp_SecureHash;
        $inputData = array();
        foreach ($request->all() as $key => $value) {
            if (substr($key, 0, 4) == "vnp_") {
                $inputData[$key] = $value;
            }
        }
        unset($inputData['vnp_SecureHash']);
        ksort($inputData);
        $hashData = "";
        $i = 0;
        foreach ($inputData as $key => $value) {
            if ($i == 1) {
                $hashData = $hashData . '&' . urlencode($key) . "=" . urlencode($value); 
            } else {
                $hashData = $hashData . urlencode($key) . "=" . urlencode($value);
                $i = 1;
            }
        }

        $secureHash = hash_hmac('sha512', $hashData, config('vnpay.vnp_HashSecret'));

        if ($secureHash == $vnp_SecureHash) {
            // Tìm booking dựa vào vnp_TxnRef (booking ID)
            $booking = Booking::find($request->vnp_TxnRef);
            
            if ($request->vnp_ResponseCode == "00") {
                // Thanh toán thành công
                $booking->update([
                    'payment_status' => 'paid',
                    'status' => 'confirmed'
                ]);
                
                return redirect()->route('front.booking.success')
                    ->with('success', 'Đặt bàn và thanh toán thành công!');
            } else {
                // Thanh toán thất bại
                $booking->update([
                    'payment_status' => 'failed',
                    'status' => 'cancelled'
                ]);
                
                return redirect()->route('front.booking.index')
                    ->with('error', 'Thanh toán không thành công. Vui lòng thử lại.');
            }
        } else {
            return redirect()->route('front.booking.index')
                ->with('error', 'Chữ ký không hợp lệ!');
        }
    }
} 
<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingMenu;
use App\Models\Category;
use App\Models\Menu;
use App\Models\Payment;
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
                return redirect()->back()
                    ->withErrors($validator)
                    ->withInput();
            }

            $validated = $validator->validated();

            // Lưu dữ liệu vào session
            session(['booking_data' => $validated]);

            // Chuyển đến trang xác nhận
            return redirect()->route('front.booking.confirm');

        } catch (\Exception $e) {
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
        $booking->status = 'cancelled';
        $booking->save();
        
        return redirect()->back()->with('success', 'Đã hủy đơn đặt bàn thành công!');
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

        return view('front.booking.confirm', compact('bookingData', 'booking', 'totalAmount'));
    }

    public function processPayment(Request $request)
    {
        try {
            $booking = Booking::findOrFail($request->booking_id);
            
            // Cập nhật trạng thái sang processing
            $booking->update([
                'payment_status' => 'processing'
            ]);

            // Lưu thời gian bắt đầu thanh toán
            session(['payment_start_time' => now()]);
            
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
            //Tính cọc 20% tổng tiền
            $depositAmount = ceil($booking->total_amount * 0.2);
            $vnp_Amount = $depositAmount * 100;
            
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
            
            // Nếu có lỗi, cập nhật trạng thái về failed
            if (isset($booking)) {
                $booking->update([
                    'payment_status' => 'failed',
                    'status' => 'cancelled'
                ]);
            }
            
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
        try {
            $bookingData = session('booking_data');
            if (!$bookingData) {
                return redirect()->route('front.booking')
                    ->with('error', 'Không tìm thấy thông tin đặt bàn');
            }

            DB::beginTransaction();

            // Tạo đơn đặt bàn mới
            $booking = new Booking();
            $booking->user_id = auth()->id(); // null nếu không đăng nhập
            $booking->name = $bookingData['name'];
            $booking->phone = $bookingData['phone'];
            $booking->booking_date = $bookingData['booking_date'];
            $booking->number_of_people = $bookingData['number_of_people'];
            $booking->special_request = $bookingData['special_request'] ?? null;
            $booking->status = 'pending';
            $booking->payment_status = 'pending';
            $booking->booking_type = $bookingData['booking_type'];
            
            // Tính tổng tiền nếu có đặt món
            if ($bookingData['booking_type'] === 'with_menu' && isset($bookingData['menu_items'])) {
                $totalAmount = 0;
                foreach ($bookingData['menu_items'] as $menuId => $item) {
                    if (isset($item['selected']) && $item['selected'] === 'on') {
                        $menu = Menu::find($menuId);
                        if ($menu) {
                            $totalAmount += $menu->price * $item['quantity'];
                        }
                    }
                }
                $booking->total_amount = $totalAmount;
            }

            $booking->save();

            // Lưu chi tiết món ăn nếu có
            if ($bookingData['booking_type'] === 'with_menu' && isset($bookingData['menu_items'])) {
                foreach ($bookingData['menu_items'] as $menuId => $item) {
                    if (isset($item['selected']) && $item['selected'] === 'on') {
                        $menu = Menu::find($menuId);
                        if ($menu) {
                            $booking->bookingMenus()->create([
                                'menu_id' => $menuId,
                                'quantity' => $item['quantity'],
                                'price' => $menu->price,
                                'subtotal' => $menu->price * $item['quantity']
                            ]);
                        }
                    }
                }
            }

            DB::commit();

            // Xóa session booking data
            session()->forget('booking_data');

            // Chuyển hướng đến trang thành công
            return redirect()->route('front.booking.success')
                ->with('success', 'Đặt bàn thành công! Vui lòng chờ nhà hàng xác nhận.');

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Booking Error: ' . $e->getMessage());
            return redirect()->route('front.booking')
                ->with('error', 'Có lỗi xảy ra khi xử lý đặt bàn. Vui lòng thử lại.');
        }
    }

    public function success()
    {
        return view('front.booking.success');
    }

    public function vnpayReturn(Request $request)
    {
        \Log::info('VNPay Return Data:', $request->all());

        try {
            // Trích xuất booking ID từ vnp_TxnRef
            $txnRef = $request->vnp_TxnRef;
            $bookingId = explode('-', $txnRef)[0];
            
            \Log::info('Looking for booking ID: ' . $bookingId);
            
            if (!$bookingId) {
                throw new \Exception('Không tìm thấy mã đơn đặt bàn');
            }

            $booking = Booking::findOrFail($bookingId);

            // Kiểm tra chữ ký và xử lý response
            if ($this->validateVNPayResponse($request)) {
                if ($request->vnp_ResponseCode == "00") {
                    // Thanh toán thành công
                    \Log::info('Payment successful for booking: ' . $bookingId);
                    
                    // Tính tiền đặt cọc (20%)
                    $depositAmount = ceil($booking->total_amount * 0.2);

                    // Lưu thông tin thanh toán
                    $payment = Payment::create([
                        'booking_id' => $booking->id,
                        'amount' => $depositAmount,
                        'payment_type' => 'deposit',
                        'payment_method' => 'vnpay',
                        'transaction_id' => $request->vnp_TransactionNo,
                        'transaction_ref' => $request->vnp_TxnRef,
                        'status' => 'completed',
                        'payment_time' => now(),
                    ]);

                    // Cập nhật trạng thái booking
                    $booking->update([
                        'payment_status' => 'paid',
                        // Không thay đổi trạng thái đặt bàn, giữ nguyên là 'pending'
                        // để chờ admin xác nhận
                    ]);

                    return redirect()->route('front.booking.success')
                        ->with('success', 'Thanh toán thành công! Vui lòng chờ nhà hàng xác nhận đơn của bạn.');
                } else {
                    // Thanh toán thất bại
                    \Log::info('Payment failed for booking: ' . $bookingId);
                    
                    // Lưu thông tin thanh toán thất bại
                    Payment::create([
                        'booking_id' => $booking->id,
                        'amount' => ceil($booking->total_amount * 0.2),
                        'payment_type' => 'deposit',
                        'payment_method' => 'vnpay',
                        'transaction_id' => $request->vnp_TransactionNo,
                        'transaction_ref' => $request->vnp_TxnRef,
                        'status' => 'failed',
                        'payment_time' => now(),
                    ]);

                    $booking->update([
                        'payment_status' => 'failed',
                        'status' => 'cancelled'
                    ]);

                    return redirect()->route('front.booking')
                        ->with('error', 'Thanh toán không thành công. Vui lòng thử lại.');
                }
            }

            // Chữ ký không hợp lệ
            Payment::create([
                'booking_id' => $booking->id,
                'amount' => ceil($booking->total_amount * 0.2),
                'payment_type' => 'deposit',
                'payment_method' => 'vnpay',
                'transaction_ref' => $request->vnp_TxnRef,
                'status' => 'failed',
                'payment_time' => now(),
            ]);

            $booking->update([
                'payment_status' => 'failed',
                'status' => 'cancelled'
            ]);

            return redirect()->route('front.booking')
                ->with('error', 'Chữ ký không hợp lệ!');

        } catch (\Exception $e) {
            \Log::error('VNPay return error: ' . $e->getMessage());
            return redirect()->route('front.booking')
                ->with('error', 'Có lỗi xảy ra trong quá trình xử lý thanh toán');
        }
    }

    // Thêm một command để tự động hủy các đơn hàng quá hạn thanh toán
    // app/Console/Commands/CancelPendingPayments.php
    public function handle()
    {
        $timeLimit = now()->subMinutes(15); // 15 phút

        $pendingBookings = Booking::where('payment_status', 'processing')
            ->where('updated_at', '<=', $timeLimit)
            ->get();

        foreach ($pendingBookings as $booking) {
            $booking->update([
                'payment_status' => 'failed',
                'status' => 'cancelled'
            ]);
            
            \Log::info('Cancelled expired payment for booking: ' . $booking->id);
        }
    }

    public function getBookingDetail(Request $request, $id)
    {
        try {
            $booking = Booking::with(['bookingMenus.menu'])
                ->where('user_id', auth()->id())
                ->findOrFail($id);

            return response()->json([
                'status' => true,
                'data' => [
                    'booking' => $booking,
                    'statusText' => [
                        'pending' => 'Đang chờ xác nhận',
                        'confirmed' => 'Đã xác nhận',
                        'cancelled' => 'Đã hủy',
                        'completed' => 'Hoàn thành'
                    ],
                    'html' => view('bookings.partials.detail-modal', compact('booking'))->render()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Không thể lấy thông tin đơn đặt bàn'
            ], 404);
        }
    }

    private function validateVNPayResponse(Request $request)
    {
        // Lấy vnp_SecureHash từ response
        $vnp_SecureHash = $request->vnp_SecureHash;
        
        // Lấy các tham số trở về từ VNPay
        $inputData = array();
        foreach ($request->all() as $key => $value) {
            if (substr($key, 0, 4) == "vnp_") {
                $inputData[$key] = $value;
            }
        }
        
        // Xóa vnp_SecureHash để tính toán hash mới
        unset($inputData['vnp_SecureHash']);
        ksort($inputData);
        
        // Tạo chuỗi hash data
        $i = 0;
        $hashData = "";
        foreach ($inputData as $key => $value) {
            if ($i == 1) {
                $hashData = $hashData . '&' . urlencode($key) . "=" . urlencode($value);
            } else {
                $hashData = urlencode($key) . "=" . urlencode($value);
                $i = 1;
            }
        }

        // Lấy vnp_HashSecret từ config
        $vnp_HashSecret = config('vnpay.hash_secret');
        
        // Tính toán checksum
        $secureHash = hash_hmac('sha512', $hashData, $vnp_HashSecret);
        
        // So sánh checksum từ VNPay với checksum tính toán
        return $vnp_SecureHash === $secureHash;
    }
} 
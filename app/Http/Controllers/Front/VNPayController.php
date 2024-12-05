<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingMenu;
use App\Models\Menu;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VNPayController extends Controller
{
    public function return(Request $request)
    {
        // Log toàn bộ dữ liệu nhận được từ VNPay
        \Log::info('VNPay return data:', $request->all());

        // Log mã phản hồi
        \Log::info('Response Code:', ['code' => $request->vnp_ResponseCode]);

        // Kiểm tra xem có phải người dùng hủy thanh toán không
        if ($request->vnp_ResponseCode == "24") {
            \Log::info('User cancelled payment');
            return redirect()->route('front.booking')
                ->with('error', 'Bạn đã hủy thanh toán');
        }
        
        // Kiểm tra mã phản hồi từ VNPay
        if ($request->vnp_ResponseCode == "00") {
            \Log::info('Payment successful, processing booking...');
            
            try {
                DB::beginTransaction();

                // Log thông tin session
                \Log::info('Session payment_info:', ['data' => session('payment_info')]);
                \Log::info('Session booking_data:', ['data' => session('booking_data')]);

                // Lấy thông tin thanh toán từ session
                $paymentInfo = session('payment_info');
                $bookingData = $paymentInfo['booking_data'];

                // Log thông tin booking sẽ được tạo
                \Log::info('Creating booking with data:', ['booking_data' => $bookingData]);

                // Tạo booking
                $booking = Booking::create([
                    'user_id' => Auth::id(),
                    'name' => $bookingData['name'],
                    'phone' => $bookingData['phone'],
                    'booking_date' => $bookingData['booking_date'],
                    'number_of_people' => $bookingData['number_of_people'],
                    'special_request' => $bookingData['special_request'] ?? null,
                    'status' => 'pending',
                    'booking_type' => $bookingData['booking_type'],
                    'total_amount' => 0
                ]);

                \Log::info('Booking created:', ['booking_id' => $booking->id]);

                // Nếu đặt bàn kèm món ăn
                if ($bookingData['booking_type'] === 'with_menu' && !empty($bookingData['menu_items'])) {
                    \Log::info('Processing menu items:', ['menu_items' => $bookingData['menu_items']]);
                    
                    $totalAmount = 0;
                    foreach ($bookingData['menu_items'] as $menuId => $item) {
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
                    \Log::info('Menu items processed, total amount:', ['total' => $totalAmount]);
                }

                // Lưu thông tin thanh toán
                Payment::create([
                    'booking_id' => $booking->id,
                    'amount' => $paymentInfo['amount'],
                    'transaction_ref' => $paymentInfo['txn_ref'],
                    'payment_method' => 'vnpay',
                    'status' => 'completed'
                ]);

                \Log::info('Payment record created');

                DB::commit();
                \Log::info('Transaction committed successfully');

                // Xóa session
                session()->forget(['booking_data', 'payment_info']);
                \Log::info('Sessions cleared');

                return redirect()->route('front.booking.success')
                    ->with('success', 'Đặt bàn thành công!');

            } catch (\Exception $e) {
                DB::rollBack();
                \Log::error('Booking creation error:', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                return redirect()->route('front.booking')
                    ->with('error', 'Có lỗi xảy ra khi xử lý đơn đặt bàn');
            }
        } else {
            // Xử lý các trường hợp lỗi khác
            $errorMessages = [
                '07' => 'Trừ tiền thành công. Giao dịch bị nghi ngờ (liên quan tới lừa đảo, giao dịch bất thường).',
                '09' => 'Giao dịch không thành công do: Thẻ/Tài khoản của khách hàng chưa đăng ký dịch vụ InternetBanking tại ngân hàng.',
                '10' => 'Giao dịch không thành công do: Khách hàng xác thực thông tin thẻ/tài khoản không đúng quá 3 lần.',
                '11' => 'Giao dịch không thành công do: Đã hết hạn chờ thanh toán.',
                '12' => 'Giao dịch không thành công do: Thẻ/Tài khoản của khách hàng bị khóa.',
                '13' => 'Giao dịch không thành công do Quý khách nhập sai mật khẩu xác thực giao dịch (OTP).',
                '24' => 'Giao dịch không thành công do: Khách hàng hủy giao dịch.',
                '51' => 'Giao dịch không thành công do: Tài khoản của quý khách không đủ số dư để thực hiện giao dịch.',
                '65' => 'Giao dịch không thành công do: Tài khoản của Quý khách đã vượt quá hạn mức giao dịch trong ngày.',
                '75' => 'Ngân hàng thanh toán đang bảo trì.',
                '79' => 'Giao dịch không thành công do: KH nhập sai mật khẩu thanh toán quá số lần quy định.',
                '99' => 'Các lỗi khác.'
            ];

            $errorMessage = $errorMessages[$request->vnp_ResponseCode] ?? 'Thanh toán không thành công.';
            \Log::error('Payment failed:', [
                'response_code' => $request->vnp_ResponseCode,
                'error_message' => $errorMessage
            ]);
            
            return redirect()->route('front.booking')
                ->with('error', $errorMessage);
        }
    }
}

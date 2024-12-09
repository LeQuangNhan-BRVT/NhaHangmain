<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Booking;
use Illuminate\Support\Facades\Log;  // Thêm Log để debug

class VNPayController extends Controller
{
    public function return(Request $request)
    {
        Log::info('VNPay Return Data:', $request->all());

        // Lưu user_id từ session nếu có
        $userId = auth()->id();
        Log::info('Current User ID: ' . $userId);

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

        $secureHash = hash_hmac('sha512', $hashData, config('vnpay.hash_secret'));

        if ($secureHash == $vnp_SecureHash) {
            // Lấy booking_id từ session
            $bookingId = session('vnpay_booking_id');
            Log::info('Looking for booking ID: ' . $bookingId);
            
            if (!$bookingId) {
                Log::error('No booking ID found in session');
                return redirect()->route('front.booking')
                    ->with('error', 'Không tìm thấy thông tin đặt bàn!');
            }

            $booking = Booking::find($bookingId);
            
            if (!$booking) {
                Log::error('Booking not found: ' . $bookingId);
                return redirect()->route('front.booking')
                    ->with('error', 'Không tìm thấy thông tin đặt bàn!');
            }
            
            if ($request->vnp_ResponseCode == "00") {
                // Thanh toán thành công
                $booking->update([
                    'payment_status' => 'paid',
                    'status' => 'confirmed'
                ]);
                
                // Chỉ xóa session liên quan đến thanh toán
                $request->session()->forget('vnpay_booking_id');
                
                Log::info('Payment successful for booking: ' . $bookingId);
                
                // Kiểm tra và duy trì session đăng nhập
                if ($userId) {
                    auth()->loginUsingId($userId);
                }
                
                return redirect()->route('front.booking.success')
                    ->with('success', 'Đặt bàn và thanh toán thành công!');
            } else {
                // Thanh toán thất bại
                $booking->update([
                    'payment_status' => 'failed',
                    'status' => 'cancelled'
                ]);
                
                // Chỉ xóa session liên quan đến thanh toán
                $request->session()->forget('vnpay_booking_id');
                
                Log::error('Payment failed for booking: ' . $bookingId . ' with response code: ' . $request->vnp_ResponseCode);
                
                // Kiểm tra và duy trì session đăng nhập
                if ($userId) {
                    auth()->loginUsingId($userId);
                }
                
                return redirect()->route('front.booking')
                    ->with('error', 'Thanh toán không thành công. Vui lòng thử lại.');
            }
        } else {
            Log::error('Invalid hash');
            
            // Kiểm tra và duy trì session đăng nhập
            if ($userId) {
                auth()->loginUsingId($userId);
            }
            
            return redirect()->route('front.booking')
                ->with('error', 'Chữ ký không hợp lệ!');
        }
    }
}

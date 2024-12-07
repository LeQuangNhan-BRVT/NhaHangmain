@extends('layouts.front')

@section('content')
<div class="container py-5">
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3>Xác nhận đặt bàn</h3>
                </div>
                <div class="card-body">
                    <!-- Thông tin đặt bàn -->
                    <h5>Thông tin đặt bàn</h5>
                    <table class="table">
                        <tr>
                            <td>Họ tên:</td>
                            <td>{{ $bookingData['name'] }}</td>
                        </tr>
                        <tr>
                            <td>Số điện thoại:</td>
                            <td>{{ $bookingData['phone'] }}</td>
                        </tr>
                        <tr>
                            <td>Ngày giờ:</td>
                            <td>{{ \Carbon\Carbon::parse($bookingData['booking_date'])->format('d/m/Y H:i') }}</td>
                        </tr>
                        <tr>
                            <td>Số người:</td>
                            <td>{{ $bookingData['number_of_people'] }}</td>
                        </tr>
                    </table>

                    @if($bookingData['booking_type'] === 'with_menu' && isset($bookingData['menu_items']))
                    <!-- Danh sách món ăn -->
                    <h5 class="mt-4">Món ăn đã chọn</h5>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Tên món</th>
                                <th>Số lượng</th>
                                <th>Đơn giá</th>
                                <th>Thành tiền</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $totalAmount = 0; @endphp
                            @foreach($bookingData['menu_items'] as $menuId => $item)
                                @if(isset($item['selected']) && $item['selected'] === 'on')
                                    @php
                                        $menu = App\Models\Menu::find($menuId);
                                        if ($menu) {
                                            $subtotal = $menu->price * $item['quantity'];
                                            $totalAmount += $subtotal;
                                        }
                                    @endphp
                                    <tr>
                                        <td>{{ $menu->name }}</td>
                                        <td>{{ $item['quantity'] }}</td>
                                        <td>{{ number_format($menu->price) }}đ</td>
                                        <td>{{ number_format($subtotal) }}đ</td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="text-end"><strong>Tổng tiền:</strong></td>
                                <td><strong>{{ number_format($totalAmount) }}đ</strong></td>
                            </tr>
                            <tr class="table-info">
                                <td colspan="3" class="text-end"><strong>Tiền đặt cọc (20%):</strong></td>
                                <td><strong>{{ number_format($totalAmount * 0.2) }}đ</strong></td>
                            </tr>
                        </tfoot>
                    </table>
                    @endif

                    <!-- Form xác nhận đặt bàn -->
                    @if(!Auth::check())
                    <form action="{{ route('front.booking.confirm') }}" method="POST" class="mt-4">
                        @csrf
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Nhân viên của chúng tôi sẽ gọi điện xác nhận đặt bàn của bạn trong thời gian sớm nhất.
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check me-2"></i>Xác nhận đặt bàn
                        </button>
                    </form>
                    @else
                    <!-- Form thanh toán VNPay cho khách đã đăng nhập -->
                    <form action="{{ route('front.booking.process-payment') }}" method="POST" class="mt-4">
                        @csrf
                        <input type="hidden" name="booking_id" value="{{ $booking->id }}">
                        <input type="hidden" name="amount" value="{{ $totalAmount * 0.2 }}">
                        <div class="alert alert-info mb-3">
                            <i class="fas fa-info-circle me-2"></i>
                            Để đảm bảo đặt bàn thành công, quý khách vui lòng đặt cọc 20% tổng hóa đơn.
                            <br>
                            Số tiền đặt cọc sẽ được trừ vào tổng hóa đơn khi thanh toán.
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-wallet me-2"></i>Đặt cọc {{ number_format($totalAmount * 0.2) }}đ qua VNPay
                        </button>
                    </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

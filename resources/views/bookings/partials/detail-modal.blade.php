<div class="modal-header">
    <h5 class="modal-title">Chi tiết đơn đặt bàn #{{ $booking->id }}</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">
    <!-- Thông tin cơ bản -->
    <div class="row mb-4">
        <div class="col-md-6">
            <p><strong>Khách hàng:</strong> {{ $booking->name }}</p>
            <p><strong>Số điện thoại:</strong> {{ $booking->phone }}</p>
            <p><strong>Ngày đặt:</strong> {{ $booking->booking_date->format('d/m/Y H:i') }}</p>
            <p><strong>Số người:</strong> {{ $booking->number_of_people }}</p>
        </div>
        <div class="col-md-6">
            <p><strong>Trạng thái:</strong> 
                <span class="badge bg-{{ $booking->status_color }}">
                    @php
                        $bookingStatus = [
                            'pending' => 'Đang chờ xác nhận',
                            'confirmed' => 'Đã xác nhận',
                            'cancelled' => 'Đã hủy',
                            'completed' => 'Hoàn thành'
                        ];
                    @endphp
                    {{ $bookingStatus[$booking->status] ?? 'Không xác định' }}
                </span>
            </p>
            <p><strong>Thanh toán:</strong>
                @switch($booking->payment_status)
                    @case('pending')
                        <span class="badge bg-warning">Chờ thanh toán</span>
                        @break
                    @case('processing')
                        <span class="badge bg-info">Đang xử lý</span>
                        @break
                    @case('paid')
                        <span class="badge bg-success">Đã thanh toán</span>
                        @break
                    @case('failed')
                        <span class="badge bg-danger">Thanh toán thất bại</span>
                        @break
                @endswitch
            </p>
            <p><strong>Ghi chú:</strong> {{ $booking->special_request ?: 'Không có' }}</p>
        </div>
    </div>

    <!-- Danh sách món đã đặt -->
    @if($booking->bookingMenus->count() > 0)
        <h6 class="mb-3">Món đã đặt:</h6>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Hình ảnh</th>
                        <th>Tên món</th>
                        <th>Số lượng</th>
                        <th>Đơn giá</th>
                        <th>Thành tiền</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($booking->bookingMenus as $item)
                    <tr>
                        <td>
                            <img src="{{ asset($item->menu->image) }}" 
                                 alt="{{ $item->menu->name }}" 
                                 style="width: 80px; height: 60px; object-fit: cover;">
                        </td>
                        <td>{{ $item->menu->name }}</td>
                        <td>{{ $item->quantity }}</td>
                        <td>{{ number_format($item->price) }}đ</td>
                        <td>{{ number_format($item->subtotal) }}đ</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="text-end"><strong>Tổng cộng:</strong></td>
                        <td><strong>{{ number_format($booking->total_amount) }}đ</strong></td>
                    </tr>
                    <tr>
                        <td colspan="4" class="text-end"><strong>Tiền đặt cọc (20%):</strong></td>
                        <td><strong>{{ number_format($booking->total_amount * 0.2) }}đ</strong></td>
                    </tr>
                    @if($booking->payment_status === 'paid')
                    <tr>
                        <td colspan="4" class="text-end text-success"><strong>Đã thanh toán cọc:</strong></td>
                        <td><strong class="text-success">{{ number_format($booking->total_amount * 0.2) }}đ</strong></td>
                    </tr>
                    <tr>
                        <td colspan="4" class="text-end text-warning"><strong>Còn lại cần thanh toán:</strong></td>
                        <td><strong class="text-warning">{{ number_format($booking->total_amount * 0.8) }}đ</strong></td>
                    </tr>
                    @endif
                </tfoot>
            </table>
        </div>

        <!-- Nút thanh toán -->
        @if($booking->payment_status === 'pending')
            <div class="text-center mt-3">
                <a href="{{ route('front.booking.confirm', $booking->id) }}" 
                   class="btn btn-primary">
                    <i class="fas fa-credit-card"></i> Đặt cọc {{ number_format($booking->total_amount * 0.2) }}đ
                </a>
            </div>
        @endif
    @else
        <div class="alert alert-info mb-0">
            Đơn đặt bàn này không có món ăn kèm theo
        </div>
    @endif
</div> 
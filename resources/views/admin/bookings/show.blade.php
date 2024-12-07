@extends('admin.layouts.app')

@section('content')
<section class="content-header">
    <div class="container-fluid my-2">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1>Chi tiết đặt bàn #{{ $booking->id }}</h1>
            </div>
            <div class="col-sm-6 text-right">
                <a href="{{ route('admin.bookings.index') }}" class="btn btn-primary">Trở về</a>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Thông tin đặt bàn</h3>
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <tr>
                                <th style="width:200px">Tên khách hàng:</th>
                                <td>{{ $booking->name }}</td>
                            </tr>
                            <tr>
                                <th>Số điện thoại:</th>
                                <td>{{ $booking->phone }}</td>
                            </tr>
                            @if($booking->user_id)
                            <tr>
                                <th>Email:</th>
                                <td>{{ $booking->user->email }}</td>
                            </tr>
                            @endif
                            <tr>
                                <th>Ngày đặt bàn:</th>
                                <td>{{ $booking->booking_date->format('d/m/Y H:i') }}</td>
                            </tr>
                            <tr>
                                <th>Số người:</th>
                                <td>{{ $booking->number_of_people }}</td>
                            </tr>
                            <tr>
                                <th>Yêu cầu đặc biệt:</th>
                                <td>{{ $booking->special_request ?: 'Không có' }}</td>
                            </tr>
                            <tr>
                                <th>Trạng thái:</th>
                                <td>
                                    <select class="form-control booking-status" data-id="{{ $booking->id }}">
                                        <option value="pending" {{ $booking->status == 'pending' ? 'selected' : '' }}>Chờ xác nhận</option>
                                        <option value="confirmed" {{ $booking->status == 'confirmed' ? 'selected' : '' }}>Đã xác nhận</option>
                                        <option value="cancelled" {{ $booking->status == 'cancelled' ? 'selected' : '' }}>Đã hủy</option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Thông tin thanh toán</h3>
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <tr>
                                <th style="width:200px">Tổng tiền:</th>
                                <td>{{ number_format($booking->total_amount) }}đ</td>
                            </tr>
                            <tr>
                                <th>Tiền đặt cọc (20%):</th>
                                <td>{{ number_format($booking->total_amount * 0.2) }}đ</td>
                            </tr>
                            @if($booking->payment_status === 'paid')
                            <tr>
                                <th>Đã thanh toán cọc:</th>
                                <td class="text-success">
                                    <strong>{{ number_format($booking->total_amount * 0.2) }}đ</strong>
                                    <i class="fas fa-check-circle"></i>
                                </td>
                            </tr>
                            <tr>
                                <th>Còn lại cần thanh toán:</th>
                                <td class="text-warning">
                                    <strong>{{ number_format($booking->total_amount * 0.8) }}đ</strong>
                                </td>
                            </tr>
                            @endif
                            <tr>
                                <th>Trạng thái thanh toán:</th>
                                <td>
                                    @if($booking->payment_status === 'pending')
                                        <span class="badge badge-warning">Chưa thanh toán</span>
                                    @elseif($booking->payment_status === 'processing')
                                        <span class="badge badge-info">Đang xử lý</span>
                                    @elseif($booking->payment_status === 'paid')
                                        <span class="badge badge-success">Đã đặt cọc</span>
                                    @elseif($booking->payment_status === 'failed')
                                        <span class="badge badge-danger">Thanh toán thất bại</span>
                                    @endif
                                </td>
                            </tr>
                            @if($booking->payment_status === 'paid' && $booking->status === 'confirmed')
                            <tr>
                                <th>Thời gian thanh toán:</th>
                                <td>{{ $booking->payment_time ? $booking->payment_time->format('d/m/Y H:i:s') : 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Mã giao dịch:</th>
                                <td>{{ $booking->transaction_id ?: 'N/A' }}</td>
                            </tr>
                            @endif
                        </table>

                        @if($booking->payment_status === 'paid' && $booking->status === 'confirmed')
                        <div class="text-center mt-3">
                            <button type="button" 
                                    class="btn btn-success" 
                                    data-toggle="modal" 
                                    data-target="#paymentModal">
                                <i class="fas fa-money-bill-wave"></i>
                                Thanh toán số tiền còn lại ({{ number_format($booking->total_amount * 0.8) }}đ)
                            </button>
                        </div>

                        <!-- Modal xác nhận thanh toán -->
                        <div class="modal fade" id="paymentModal" tabindex="-1" role="dialog">
                            <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Xác nhận thanh toán</h5>
                                        <button type="button" class="close" data-dismiss="modal">
                                            <span>&times;</span>
                                        </button>
                                    </div>
                                    <div class="modal-body">
                                        <p>Xác nhận thanh toán số tiền còn lại:</p>
                                        <table class="table">
                                            <tr>
                                                <th>Tổng hóa đơn:</th>
                                                <td class="text-end">{{ number_format($booking->total_amount) }}đ</td>
                                            </tr>
                                            <tr>
                                                <th>Đã đặt cọc:</th>
                                                <td class="text-end text-success">
                                                    {{ number_format($booking->total_amount * 0.2) }}đ
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Số tiền cần thanh toán:</th>
                                                <td class="text-end text-danger">
                                                    <strong>{{ number_format($booking->total_amount * 0.8) }}đ</strong>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Hủy</button>
                                        <button type="button" class="btn btn-success confirm-payment" data-id="{{ $booking->id }}">
                                            <i class="fas fa-check"></i> Xác nhận đã thanh toán
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            @if($booking->bookingMenus->count() > 0)
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Danh sách món ăn</h3>
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Món ăn</th>
                                    <th>Số lượng</th>
                                    <th>Đơn giá</th>
                                    <th>Thành tiền</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($booking->bookingMenus as $item)
                                <tr>
                                    <td>{{ $item->menu->name }}</td>
                                    <td>{{ $item->quantity }}</td>
                                    <td>{{ number_format($item->price) }}đ</td>
                                    <td>{{ number_format($item->subtotal) }}đ</td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="3">Tổng cộng:</th>
                                    <th>{{ number_format($booking->total_amount) }}đ</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</section>
@endsection

@section('customjs')
<script>
$(document).ready(function() {
    $('.booking-status').change(function() {
        var bookingId = $(this).data('id');
        var status = $(this).val();
        
        $.ajax({
            url: '/admin/bookings/' + bookingId + '/status',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                status: status
            },
            success: function(response) {
                if(response.status) {
                    alert('Cập nhật trạng thái thành công');
                }
            },
            error: function() {
                alert('Có lỗi xảy ra');
            }
        });
    });

    $('.confirm-payment').click(function() {
        var bookingId = $(this).data('id');
        
        $.ajax({
            url: '/admin/bookings/' + bookingId + '/complete-payment',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if(response.success) {
                    $('#paymentModal').modal('hide');
                    alert('Thanh toán thành công!');
                    location.reload();
                } else {
                    alert('Có lỗi xảy ra: ' + response.message);
                }
            },
            error: function() {
                alert('Có lỗi xảy ra khi xử lý thanh toán');
            }
        });
    });
});
</script>
@endsection 
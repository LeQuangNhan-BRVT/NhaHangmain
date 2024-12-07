@extends('admin.layouts.app')

@section('content')
<section class="content-header">
    <div class="container-fluid my-2">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1>Danh sách đặt bàn</h1>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <!-- Bộ lọc -->
        <div class="card mb-3">
            <div class="card-body">
                <form action="{{ route('admin.bookings.index') }}" method="GET" id="filterForm">
                    <div class="row">
                        <div class="col-md-3 mb-2">
                            <input type="text" name="search" class="form-control" 
                                   placeholder="Tìm theo tên, SĐT, số người..." 
                                   value="{{ request('search') }}">
                        </div>
                        <div class="col-md-2 mb-2">
                            <select name="status" class="form-control">
                                <option value="">Tất cả trạng thái</option>
                                <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Chờ xác nhận</option>
                                <option value="confirmed" {{ request('status') == 'confirmed' ? 'selected' : '' }}>Đã xác nhận</option>
                                <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Đã hủy</option>
                                <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Hoàn thành</option>
                            </select>
                        </div>
                        <div class="col-md-2 mb-2">
                            <select name="payment_status" class="form-control">
                                <option value="">Tất cả TT thanh toán</option>
                                <option value="pending" {{ request('payment_status') == 'pending' ? 'selected' : '' }}>Chờ thanh toán</option>
                                <option value="processing" {{ request('payment_status') == 'processing' ? 'selected' : '' }}>Đang xử lý</option>
                                <option value="paid" {{ request('payment_status') == 'paid' ? 'selected' : '' }}>Đã thanh toán</option>
                                <option value="failed" {{ request('payment_status') == 'failed' ? 'selected' : '' }}>Thanh toán thất bại</option>
                            </select>
                        </div>
                        <div class="col-md-2 mb-2">
                            <input type="date" name="date" class="form-control" value="{{ request('date') }}">
                        </div>
                        <div class="col-md-3 mb-2">
                            <button type="submit" class="btn btn-primary">Lọc</button>
                            <a href="{{ route('admin.bookings.index') }}" class="btn btn-secondary">Đặt lại</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body table-responsive p-0">
                <table class="table table-hover text-nowrap">
                    <thead>
                        <tr>
                            <th>
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'id', 'direction' => request('direction') == 'asc' ? 'desc' : 'asc']) }}">
                                    ID {!! getSortIcon('id') !!}
                                </a>
                            </th>
                            <th>Khách hàng</th>
                            <th>Số điện thoại</th>
                            <th>
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'booking_date', 'direction' => request('direction') == 'asc' ? 'desc' : 'asc']) }}">
                                    Ngày đặt {!! getSortIcon('booking_date') !!}
                                </a>
                            </th>
                            <th>
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'number_of_people', 'direction' => request('direction') == 'asc' ? 'desc' : 'asc']) }}">
                                    Số người {!! getSortIcon('number_of_people') !!}
                                </a>
                            </th>
                            <th>
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'booking_type', 'direction' => request('direction') == 'asc' ? 'desc' : 'asc']) }}">
                                    Loại đặt bàn {!! getSortIcon('booking_type') !!}
                                </a>
                            </th>
                            <th>
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'total_amount', 'direction' => request('direction') == 'asc' ? 'desc' : 'asc']) }}">
                                    Tổng tiền {!! getSortIcon('total_amount') !!}
                                </a>
                            </th>
                            <th>
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'status', 'direction' => request('direction') == 'asc' ? 'desc' : 'asc']) }}">
                                    Trạng thái {!! getSortIcon('status') !!}
                                </a>
                            </th>
                            <th>
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'payment_status', 'direction' => request('direction') == 'asc' ? 'desc' : 'asc']) }}">
                                    Thanh toán {!! getSortIcon('payment_status') !!}
                                </a>
                            </th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($bookings as $booking)
                        <tr>
                            <td>{{ $booking->id }}</td>
                            <td>
                                {{ $booking->name }}
                                @if($booking->user_id)
                                    <br><small class="text-muted">Tài khoản: {{ $booking->user->email }}</small>
                                @endif
                            </td>
                            <td>{{ $booking->phone }}</td>
                            <td>{{ $booking->booking_date->format('d/m/Y H:i') }}</td>
                            <td>{{ $booking->number_of_people }}</td>
                            <td>
                                @if($booking->bookingMenus->count() > 0)
                                    <span class="badge badge-success">Đặt kèm món</span>
                                @else
                                    <span class="badge badge-info">Chỉ đặt bàn</span>
                                @endif
                            </td>
                            <td>
                                @if($booking->bookingMenus->count() > 0)
                                    {{ number_format($booking->total_amount) }}đ
                                @else
                                    -
                                @endif
                            </td>
                            <td>
                                <select class="form-control booking-status" data-id="{{ $booking->id }}">
                                    <option value="pending" {{ $booking->status == 'pending' ? 'selected' : '' }}>Chờ xác nhận</option>
                                    <option value="confirmed" {{ $booking->status == 'confirmed' ? 'selected' : '' }}>Đã xác nhận</option>
                                    <option value="cancelled" {{ $booking->status == 'cancelled' ? 'selected' : '' }}>Đã hủy</option>
                                </select>
                            </td>
                            <td>
                                @switch($booking->payment_status)
                                    @case('pending')
                                        <span class="badge badge-warning">Chờ thanh toán</span>
                                        @break
                                    @case('processing')
                                        <span class="badge badge-info">Đang xử lý</span>
                                        @break
                                    @case('paid')
                                        <span class="badge badge-success">Đã thanh toán</span>
                                        @break
                                    @case('failed')
                                        <span class="badge badge-danger">Thanh toán thất bại</span>
                                        @break
                                    @default
                                        <span class="badge badge-secondary">Không xác định</span>
                                @endswitch
                            </td>
                            <td>
                                <div class="btn-group action-buttons">
                                    <a href="{{ route('admin.bookings.edit', $booking->id) }}" 
                                       class="btn btn-info btn-sm mx-1" 
                                       title="Chỉnh sửa">
                                        <i class="fas fa-edit"></i>
                                    </a>

                                    <a href="{{ route('admin.bookings.show', $booking->id) }}" 
                                       class="btn btn-primary btn-sm mx-1"
                                       title="Xem chi tiết">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    
                                    <button type="button" 
                                            class="btn btn-danger btn-sm delete-booking mx-1" 
                                            data-id="{{ $booking->id }}"
                                            title="Xóa">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" class="text-center">Không có đơn đặt bàn nào</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                <div class="d-flex justify-content-center">
                    {{ $bookings->links() }}
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@section('customjs')
<script>
$(document).ready(function() {
    // Cập nhật trạng thái
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

    // Xóa đặt bàn
    $('.delete-booking').click(function() {
        if(confirm('Bạn có chắc chắn muốn xóa đơn đặt bàn này?')) {
            var bookingId = $(this).data('id');
            $.ajax({
                url: '/admin/bookings/' + bookingId,
                type: 'DELETE',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if(response.status) {
                        window.location.reload();
                    }
                }
            });
        }
    });
});

function getSortIcon(field) {
    const currentSort = '{{ request('sort') }}';
    const currentDirection = '{{ request('direction') }}';
    
    if (currentSort !== field) return '';
    
    return currentDirection === 'asc' 
        ? '<i class="fas fa-sort-up ml-1"></i>' 
        : '<i class="fas fa-sort-down ml-1"></i>';
}
</script>
@endsection 
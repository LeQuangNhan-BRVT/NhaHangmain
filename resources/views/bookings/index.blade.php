@extends('layouts.front')

@section('title', 'Lịch sử đặt bàn')

@section('content')
<div class="container py-5">
    <div class="row">
        <div class="col-12">
            <h2 class="mb-4">Lịch sử đặt bàn</h2>
            
            @if(!isset($bookings) || $bookings->isEmpty())
                <div class="alert alert-info">
                    <p class="mb-0">Bạn chưa có đơn đặt bàn nào.</p>
                    <p class="mb-0 mt-2">
                        <a href="{{ route('front.booking') }}" class="alert-link">Đặt bàn ngay</a> để trải nghiệm dịch vụ của chúng tôi!
                    </p>
                </div>
            @else
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Mã đơn</th>
                                        <th>Ngày đặt</th>
                                        <th>Giờ đặt</th>
                                        <th>Số người</th>
                                        <th>Trạng thái</th>
                                        <th>Ghi chú</th>
                                        <th>Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($bookings as $booking)
                                    <tr>
                                        <td>#{{ $booking->id }}</td>
                                        <td>{{ $booking->booking_date->format('d/m/Y') }}</td>
                                        <td>{{ $booking->booking_date->format('H:i') }}</td>
                                        <td>{{ $booking->number_of_people }}</td>
                                        <td>
                                            <span class="badge bg-{{ $booking->status_color }}">
                                                @php
                                                    $statusText = [
                                                        'pending' => 'Đang chờ xác nhận',
                                                        'confirmed' => 'Đã xác nhận',
                                                        'cancelled' => 'Đã hủy',
                                                        'completed' => 'Hoàn thành'
                                                    ];
                                                @endphp
                                                {{ $statusText[$booking->status] ?? 'Không xác định' }}
                                            </span>
                                        </td>
                                        <td>{{ $booking->special_request ?: 'Không có' }}</td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                @if($booking->can_edit)
                                                    <a href="{{ route('bookings.edit', $booking->id) }}" 
                                                       class="btn btn-warning btn-sm">
                                                        <i class="fas fa-edit"></i> Sửa
                                                    </a>
                                                @endif

                                                @if($booking->status === 'pending')
                                                    <form action="{{ route('bookings.cancel', $booking->id) }}" 
                                                          method="POST" 
                                                          class="d-inline" 
                                                          onsubmit="return confirm('Bạn có chắc chắn muốn hủy đơn này?');">
                                                        @csrf
                                                        @method('PUT')
                                                        <button type="submit" class="btn btn-danger btn-sm">
                                                            <i class="fas fa-times"></i> Hủy đơn
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @if($bookings->hasPages())
                            <div class="mt-4">
                                {{ $bookings->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
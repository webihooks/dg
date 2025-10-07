class Order {
  final int orderId;
  final String customerName;
  final String customerPhone;
  final String orderType;
  final String deliveryAddress;
  final String tableNumber;
  final String status;
  final double subtotal;
  final double discountAmount;
  final String discountType;
  final double gstAmount;
  final double deliveryCharge;
  final double totalAmount;
  final DateTime createdAt;
  final DateTime updatedAt;
  final String orderNotes;
  final int itemCount;
  final List<OrderItem> items;
  final int timerRemaining;
  final bool isDelayed;
  final bool isCompletedOnTime;

  Order({
    required this.orderId,
    required this.customerName,
    required this.customerPhone,
    required this.orderType,
    required this.deliveryAddress,
    required this.tableNumber,
    required this.status,
    required this.subtotal,
    required this.discountAmount,
    required this.discountType,
    required this.gstAmount,
    required this.deliveryCharge,
    required this.totalAmount,
    required this.createdAt,
    required this.updatedAt,
    required this.orderNotes,
    required this.itemCount,
    required this.items,
    required this.timerRemaining,
    required this.isDelayed,
    required this.isCompletedOnTime,
  });

  factory Order.fromJson(Map<String, dynamic> json) {
    return Order(
      orderId: int.parse(json['order_id'].toString()),
      customerName: json['customer_name'] ?? 'Unknown Customer',
      customerPhone: json['customer_phone'] ?? '',
      orderType: json['order_type'] ?? 'delivery',
      deliveryAddress: json['delivery_address'] ?? '',
      tableNumber: json['table_number']?.toString() ?? '',
      status: json['status'] ?? 'Pending',
      subtotal: double.parse(json['subtotal']?.toString() ?? '0'),
      discountAmount: double.parse(json['discount_amount']?.toString() ?? '0'),
      discountType: json['discount_type'] ?? '',
      gstAmount: double.parse(json['gst_amount']?.toString() ?? '0'),
      deliveryCharge: double.parse(json['delivery_charge']?.toString() ?? '0'),
      totalAmount: double.parse(json['total_amount']?.toString() ?? '0'),
      createdAt: DateTime.parse(json['created_at']),
      updatedAt: DateTime.parse(json['updated_at'] ?? json['created_at']),
      orderNotes: json['order_notes'] ?? '',
      itemCount: int.parse(json['item_count']?.toString() ?? '0'),
      items: (json['items'] as List<dynamic>? ?? [])
          .map((item) => OrderItem.fromJson(item))
          .toList(),
      timerRemaining: int.parse(json['timer_remaining']?.toString() ?? '0'),
      isDelayed: json['is_delayed'] == true,
      isCompletedOnTime: json['is_completed_on_time'] == true,
    );
  }

  String get formattedDate {
    return '${createdAt.day}/${createdAt.month}/${createdAt.year} ${createdAt.hour}:${createdAt.minute.toString().padLeft(2, '0')}';
  }

  String get formattedOrderType {
    if (orderType == 'dining') {
      return 'Dining - Table $tableNumber';
    }
    return orderType[0].toUpperCase() + orderType.substring(1);
  }

  // Return color name instead of Color object
  String get statusColorName {
    switch (status.toLowerCase()) {
      case 'pending':
        return 'amber';
      case 'confirmed':
        return 'cyan';
      case 'preparing':
        return 'orange';
      case 'ready':
        return 'green';
      case 'completed':
        return 'orange';
      case 'cancelled':
        return 'red';
      default:
        return 'grey';
    }
  }

  bool get canUpdateStatus => ['Pending', 'Confirmed', 'Preparing', 'Ready'].contains(status);
  bool get canMarkReady => ['Pending', 'Confirmed', 'Preparing'].contains(status);
  bool get canMarkComplete => status == 'Ready';
  bool get canCancel => ['Pending', 'Confirmed', 'Preparing'].contains(status);
}

class OrderItem {
  final String productName;
  final double price;
  final int quantity;

  OrderItem({
    required this.productName,
    required this.price,
    required this.quantity,
  });

  factory OrderItem.fromJson(Map<String, dynamic> json) {
    return OrderItem(
      productName: json['product_name'] ?? 'Unknown Item',
      price: double.parse(json['price']?.toString() ?? '0'),
      quantity: int.parse(json['quantity']?.toString() ?? '1'),
    );
  }

  double get total => price * quantity;
}
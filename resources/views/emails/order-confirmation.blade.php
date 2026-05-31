<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #4CAF50;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 5px 5px 0 0;
        }
        .content {
            background-color: #f9f9f9;
            padding: 20px;
            border: 1px solid #ddd;
        }
        .order-details {
            background-color: white;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
            border-left: 4px solid #4CAF50;
        }
        .item {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        .item:last-child {
            border-bottom: none;
        }
        .total {
            font-size: 18px;
            font-weight: bold;
            color: #4CAF50;
            margin-top: 15px;
            text-align: right;
        }
        .footer {
            text-align: center;
            padding: 20px;
            color: #777;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Order Confirmation</h1>
    </div>
    
    <div class="content">
        <p>Dear {{ $user->name }},</p>
        
        <p>Thank you for your order! We have received your order and it is being processed.</p>
        
        <div class="order-details">
            <h3>Order Details</h3>
            <p><strong>Order Date:</strong> {{ $orderDate }}</p>
            
            <h4>Items Ordered:</h4>
            @foreach($items as $item)
            <div class="item">
                <strong>{{ $item->product->name ?? 'Product #' . $item->product_id }}</strong><br>
                Quantity: {{ $item->quantity }}<br>
                @if($item->product)
                Price: {{ number_format($item->product->price, 2) }} DT each<br>
                Subtotal: {{ number_format($item->product->price * $item->quantity, 2) }} DT
                @endif
            </div>
            @endforeach
            
            <div class="total">
                Total: {{ number_format($total, 2) }} DT
            </div>
        </div>
        
        <p>We will send you another email once your order has been shipped.</p>
        
        <p>If you have any questions, please don't hesitate to contact us.</p>
        
        <p>Best regards,<br>E-Commerce Team</p>
    </div>
    
    <div class="footer">
        <p>This is an automated email. Please do not reply to this message.</p>
    </div>
</body>
</html>


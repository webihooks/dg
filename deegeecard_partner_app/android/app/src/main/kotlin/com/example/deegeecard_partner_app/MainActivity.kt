package com.example.deegeecard_partner_app

import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.os.Build
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat

class MainActivity: FlutterActivity() {
    private val CHANNEL = "com.deegeecard/foreground_service"
    
    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        
        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, CHANNEL).setMethodCallHandler { call, result ->
            when (call.method) {
                "startForegroundService" -> {
                    result.success("Foreground service simulation started")
                }
                "stopForegroundService" -> {
                    result.success("Foreground service simulation stopped")
                }
                "showOrderNotification" -> {
                    val orderId = call.argument<String>("orderId") ?: "Unknown"
                    val customerName = call.argument<String>("customerName") ?: "Customer"
                    val totalAmount = call.argument<Double>("totalAmount") ?: 0.0
                    
                    showOrderNotification(orderId, customerName, totalAmount)
                    result.success("Order notification shown")
                }
                "isServiceRunning" -> {
                    result.success(false) // Simulated service
                }
                else -> result.notImplemented()
            }
        }
    }
    
    private fun showOrderNotification(orderId: String, customerName: String, totalAmount: Double) {
        createNotificationChannel()
        
        val notificationIntent = Intent(this, MainActivity::class.java)
        val pendingIntent = PendingIntent.getActivity(
            this,
            0, notificationIntent, PendingIntent.FLAG_IMMUTABLE
        )
        
        val notification = NotificationCompat.Builder(this, "NewOrdersChannel")
            .setContentTitle("New Order Received! 🎉")
            .setContentText("Order #$orderId from $customerName - ₹$totalAmount")
            .setSmallIcon(R.mipmap.ic_launcher)
            .setContentIntent(pendingIntent)
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setAutoCancel(true)
            .build()
        
        with(NotificationManagerCompat.from(this)) {
            notify(System.currentTimeMillis().toInt(), notification)
        }
    }
    
    private fun createNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val channel = NotificationChannel(
                "NewOrdersChannel",
                "New Orders",
                NotificationManager.IMPORTANCE_HIGH
            ).apply {
                description = "Notifications for new orders"
                setShowBadge(true)
            }
            
            val manager = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
            manager.createNotificationChannel(channel)
        }
    }
}
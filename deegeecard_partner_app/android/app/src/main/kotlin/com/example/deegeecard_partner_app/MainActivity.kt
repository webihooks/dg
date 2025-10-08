package com.example.deegeecard_partner_app

import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel
import android.content.Intent
import android.os.Build

class MainActivity: FlutterActivity() {
    private val CHANNEL = "com.deegeecard/foreground_service"
    
    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        
        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, CHANNEL).setMethodCallHandler { call, result ->
            when (call.method) {
                "startForegroundService" -> {
                    // For now, just return success without actual service
                    // We'll implement the actual service later
                    result.success("Service started (simulated)")
                }
                "stopForegroundService" -> {
                    // For now, just return success without actual service
                    result.success("Service stopped (simulated)")
                }
                else -> result.notImplemented()
            }
        }
    }
}
package com.example.deegeecard_partner_app

import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel
import android.content.Intent
import android.os.Build
import android.util.Log

class MainActivity: FlutterActivity() {
    private val CHANNEL = "com.deegeecard/foreground_service"
    private val TAG = "MainActivity"
    
    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        
        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, CHANNEL).setMethodCallHandler { call, result ->
            when (call.method) {
                "startForegroundService" -> {
                    Log.d(TAG, "Start foreground service requested")
                    result.success("Service start requested (Phase 1 simulated)")
                }
                "stopForegroundService" -> {
                    Log.d(TAG, "Stop foreground service requested")
                    result.success("Service stop requested (Phase 1 simulated)")
                }
                "isServiceRunning" -> {
                    result.success(false) // Always false for simulated service
                }
                else -> result.notImplemented()
            }
        }
    }
}
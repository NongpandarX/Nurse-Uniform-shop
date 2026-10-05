<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserDevice;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DeviceController extends Controller
{
    public function index(): View
    {
        $devices = UserDevice::with('user.branch')->latest('id')->paginate(20);
        return view('admin.devices.index', compact('devices'));
    }

    public function approve(int $id): RedirectResponse
    {
        $device = UserDevice::findOrFail($id);
        $device->update(['is_approved' => true]);

        return back()->with('success', 'อนุมัติอุปกรณ์เรียบร้อยแล้ว');
    }

    public function revoke(int $id): RedirectResponse
    {
        $device = UserDevice::findOrFail($id);
        $device->update(['is_approved' => false]);

        return back()->with('success', 'เพิกถอนสิทธิ์อุปกรณ์เรียบร้อยแล้ว');
    }
}

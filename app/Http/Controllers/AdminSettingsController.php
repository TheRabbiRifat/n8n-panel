<?php

namespace App\Http\Controllers;

use App\Models\GlobalSetting;
use Illuminate\Http\Request;

class AdminSettingsController extends Controller
{
    public function index()
    {
        $this->authorize('manage_settings');
        $settings = GlobalSetting::where('key', 'like', 'panel_%')->get()->pluck('value', 'key');

        return view('admin.settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        $this->authorize('manage_settings');
        $request->validate([
            'panel_app_name' => 'required|string|max:255',
            'panel_footer_text' => 'nullable|string|max:255',
            'panel_registration_enabled' => 'nullable|boolean',
        ]);

        $data = [
            'panel_app_name' => $request->panel_app_name,
            'panel_footer_text' => $request->panel_footer_text,
            'panel_registration_enabled' => $request->has('panel_registration_enabled') ? 'true' : 'false',
        ];

        foreach ($data as $key => $value) {
            GlobalSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        return back()->with('success', 'Panel settings updated.');
    }
}

<x-mail::message>
# 🎉 Welcome to EduCBT, {{ $adminName }}!

**{{ $tenant->name }}** is now **LIVE**! Your school's dedicated platform is ready.

<x-mail::panel class="bg-gradient-to-r from-gray-800 to-gray-900 dark:from-gray-900 dark:to-black text-white">
📍 **School URL:** `https://{{ $tenant->handle }}.{{ config('app.central_domain') }}`
<br>🔐 **Admin Email:** `{{ $adminEmail }}`
</x-mail::panel>

<x-mail::button :url="$loginUrl" color="primary" class="bg-gradient-to-r from-green-500 to-emerald-600 text-white font-bold">
🚀 Start School Setup
</x-mail::button>

<x-mail::panel>
**Next Steps:**
✅ Add teachers & students  
✅ Create exam sessions
✅ Import existing data
</x-mail::panel>

**Need help?** Reply to this email or contact support@educbt.com

Cheers! 🍻<br>
**EduCBT Team**
{{ config('app.url') }}
</x-mail::message>
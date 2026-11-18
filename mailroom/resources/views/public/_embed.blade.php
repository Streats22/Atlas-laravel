<form action="{{ route('public.subscribe', $audience->public_id) }}" method="post" style="display:grid;gap:8px;max-width:360px;font-family:sans-serif">
  <label>Email <input type="email" name="email" required style="width:100%;padding:8px"></label>
  <label>First name <input type="text" name="first_name" style="width:100%;padding:8px"></label>
  <div style="position:absolute;left:-5000px" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
  <button type="submit" style="padding:10px;background:#4f46e5;color:#fff;border:0;border-radius:6px">Subscribe</button>
</form>

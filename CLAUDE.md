# Proje Kuralları (CLAUDE.md)

> Bu dosya, Claude'un PR'ları incelerken uyacağı kuralları içerir.
> **Kuralları ÇOK SIKI uygula. Tek bir ihlali bile gözden kaçırma.**

## Proje Hakkında

- **Framework:** Laravel (PHP)
- **Mimari:** Controller → Service → Repository → Model
- **ORM:** Eloquent
- **Amaç:** AI kod incelemesini test etmek için demo proje

---

## 🚨 KRİTİK: N+1 SORGU TESPİTİ

**N+1, bu projede #1 öncelikli BLOCKER'dır.** Her PR'da N+1 olup olmadığını **MUTLAKA** kontrol et.

### N+1 nasıl anlaşılır?

Aşağıdakilerin **HEPSİ** N+1 işaretidir — gördüğünde **🚨 BLOCKER** olarak işaretle:

1. **Foreach içinde ilişki erişimi** (eager loading olmadan):
   ```php
   // ❌ N+1
   $users = User::all();
   foreach ($users as $user) {
       echo $user->posts->count(); // her iterasyonda yeni query
   }
   ```

2. **Foreach içinde lazy load:**
   ```php
   // ❌ N+1
   foreach ($orders as $order) {
       echo $order->customer->name; // customer her seferinde fetch
   }
   ```

3. **Collection map/each içinde ilişki erişimi:**
   ```php
   // ❌ N+1
   $posts->map(fn($p) => $p->author->name);
   ```

4. **Blade template'de loop içinde ilişki:**
   ```blade
   {{-- ❌ N+1 --}}
   @foreach($users as $user)
       {{ $user->profile->avatar }}
   @endforeach
   ```

5. **Repository/Service'te `->get()` sonrası loop'ta ilişki erişimi.**

### Çözüm önerileri (yorumda mutlaka belirt):

- **`with()` ile eager load:** `User::with('posts')->get()`
- **`load()` ile lazy eager load:** `$users->load('posts')`
- **`withCount()` count için:** `User::withCount('posts')->get()`
- **`loadCount()`:** `$users->loadCount('posts')`
- **Nested:** `User::with('posts.comments')->get()`

### N+1 yorumu şu formatta yaz:

```
🚨 BLOCKER — N+1 Query

Bu döngüde her iterasyonda yeni bir DB sorgusu çalışıyor.
N kullanıcı için N+1 query oluşur (büyük ölçekte yavaşlar).

Çözüm:
$users = User::with('posts')->get();
```

---

## Service & Repository Pattern Kuralları

### Repository
- **SADECE** veritabanı erişimi yapmalı (Eloquent query'leri).
- İş mantığı **YASAK** — sadece CRUD ve query metodları.
- Her metod tek bir veri operasyonu yapmalı.
- Interface'i olmalı (örn. `UserRepositoryInterface`).
- Eager loading repository içinde yapılmalı, controller'a bırakılmamalı.

```php
// ✅ Doğru
class UserRepository {
    public function getActiveWithPosts(): Collection {
        return User::with('posts')->where('active', true)->get();
    }
}

// ❌ Yanlış — iş mantığı repository'de
class UserRepository {
    public function getActiveAndSendEmail() { ... }
}
```

### Service
- **İş mantığı** burada — validation, hesaplama, orchestration.
- Repository'yi dependency injection ile alır.
- **DOĞRUDAN** Eloquent kullanmaz, repository üzerinden gider.
- Tek bir işi yapmalı (Single Responsibility).
- Transaction yönetimi service'te yapılır (`DB::transaction`).

```php
// ✅ Doğru
class OrderService {
    public function __construct(private OrderRepository $orders) {}

    public function placeOrder(array $data): Order {
        return DB::transaction(fn() => $this->orders->create($data));
    }
}

// ❌ Yanlış — service'te direkt Eloquent
class OrderService {
    public function placeOrder() {
        Order::create(...); // repository kullanmalı
    }
}
```

### Controller
- **İnce** olmalı (thin controller). Sadece request → service → response.
- İş mantığı **YASAK**.
- Validation Request class'ında veya `validate()` ile.
- Service'i DI ile al.

---

## Eloquent / Veritabanı Kuralları

- **`SELECT *` yasak** — sadece gereken kolonlar (`select(['id', 'name'])`).
- **Mass assignment** için `$fillable` veya `$guarded` mutlaka tanımlı olmalı.
- **`->first()` yerine bulunamayabilirse `->firstOrFail()`** kullan.
- **Raw query** sadece zorunlu durumlarda — parametrize edilmiş olmalı (SQL injection!).
- **Pagination** — listelerde `->paginate()` kullanılmalı, `->get()` ile tüm tabloyu çekme.
- **Index kontrolü:** `where()` veya `orderBy()` kullanılan kolonlar indexli olmalı (migration'da kontrol et).

---

## Güvenlik

- **SQL Injection:** Raw query'lerde mutlaka binding kullan (`?` veya named).
- **XSS:** Blade'de `{!! !!}` kullanımına dikkat — kullanıcı verisinde **YASAK**.
- **CSRF:** Form'larda `@csrf` direktifi olmalı.
- **Mass Assignment:** `Model::create($request->all())` **YASAK** — `$request->validated()` kullan.
- **Authorization:** Kullanıcı verisine erişimde policy/gate kontrolü olmalı.
- **Secret/.env:** Asla commit edilmemeli.

---

## Kod Stili

- PSR-12 takip edilmeli.
- Method/değişken isimleri **camelCase**, class isimleri **PascalCase**.
- Type hint **zorunlu** — parametre ve return type yazılmalı.
- `declare(strict_types=1);` dosya başında olabilir (öneri).
- Method'lar 50 satırı geçmemeli.
- Magic number yasak — `const` veya config kullan.

---

## Test

- Yeni service/repository için **feature test** veya **unit test** yazılmalı.
- N+1 testi için `DB::listen()` ile query sayısı kontrol edilebilir.

---

## İnceleme Tonu

- **🚨 BLOCKER** — Mutlaka düzeltilmeli (N+1, güvenlik açığı, mimari ihlal).
- **⚠️ WARNING** — Düzeltilmesi önerilir (kod kokusu, performans riski).
- **💡 SUGGESTION** — İyileştirme önerisi (stil, refactor).
- **✅ NICE** — İyi yapılmış kısımları da belirt.

İnline comment'leri spesifik satıra bırak. Sonunda kısa bir özet yaz.

---

## Görmezden Gel

- Vendor klasöründeki dosyalar.
- Auto-generated migration timestamp'leri.
- `composer.lock` değişiklikleri (sadece güvenlik açığı varsa belirt).

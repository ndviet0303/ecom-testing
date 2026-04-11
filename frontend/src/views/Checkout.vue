<script setup>
import { ref, onMounted, computed, watch } from "vue";
import { useCartStore } from "@/stores/cartStore";
import { useAuthStore } from "@/stores/authStore";
import { useToastStore } from "@/stores/toastStore";
import { useRoute, useRouter } from "vue-router";
import axios from "axios";
import { MapPin, Truck, CreditCard, CheckCircle } from "lucide-vue-next";
import administrativeUnits from "@/data/vn-administrative-units.json";

const cartStore = useCartStore();
const authStore = useAuthStore();
const toastStore = useToastStore();
const router = useRouter();
const route = useRoute();

const addresses = ref([]);
const fulfillmentMethod = ref("shipping");
const selectedAddressId = ref(null);
const note = ref("");
const loading = ref(false);
const savingAddress = ref(false);
const showAddressForm = ref(false);
const newAddress = ref({
  label: "Nhà riêng",
  recipient_name: "",
  phone: "",
  line1: "",
  ward: "",
  district: "",
  province: "",
  is_default: true,
});
const selectedProvinceCode = ref("");
const couponCodeInput = ref("");
const appliedCoupon = ref(null);
const couponChecking = ref(false);
const STORAGE_KEY = "checkout_coupon_code";

const fetchData = async () => {
  if (!authStore.isLoggedIn) {
    router.push("/login");
    return;
  }

  try {
    const aResp = await axios.get("/api/v1/addresses");
    addresses.value = aResp.data.data || aResp.data;

    if (addresses.value.length > 0) {
      const def =
        addresses.value.find((a) => a.is_default) || addresses.value[0];
      selectedAddressId.value = def.id;
    }
  } catch (err) {
    console.error("Lỗi tải dữ liệu checkout:", err);
  }
};

const INNER_CITY_PROVINCE = "Thành phố Hà Nội";
const DEFAULT_SHIPPING_FEE = 30_000;
const HANOI_INNER_DISTRICTS = [
  "Ba Đình",
  "Hoàn Kiếm",
  "Đống Đa",
  "Hai Bà Trưng",
  "Hoàng Mai",
  "Thanh Xuân",
  "Cầu Giấy",
  "Tây Hồ",
  "Long Biên",
  "Hà Đông",
  "Nam Từ Liêm",
  "Bắc Từ Liêm",
];

const selectedAddress = computed(
  () => addresses.value.find((a) => a.id === selectedAddressId.value) || null,
);
const provinceOptions = administrativeUnits;
const districtOptions = computed(() => {
  const province = provinceOptions.find(
    (item) => item.Code === selectedProvinceCode.value,
  );
  const fromDataset = (province?.Wards || []).map((item) => item.FullName);

  if (fromDataset.length > 0) {
    return fromDataset;
  }

  const fromAddresses = addresses.value
    .map((addr) => String(addr?.district || "").trim())
    .filter((v) => v !== "");

  return [...new Set(fromAddresses)];
});

const normalizeText = (value) => {
  return String(value || "")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase()
    .trim();
};

const isInnerCityAddress = (address) => {
  if (!address?.province || !address?.district) return false;

  const isHanoi =
    normalizeText(address.province) === normalizeText(INNER_CITY_PROVINCE);
  if (!isHanoi) return false;

  const district = normalizeText(address.district);
  return HANOI_INNER_DISTRICTS.map((item) => normalizeText(item)).includes(
    district,
  );
};

const shippingFee = computed(() => {
  if (fulfillmentMethod.value === "pickup") return 0;
  if (!selectedAddress.value) return DEFAULT_SHIPPING_FEE;

  return isInnerCityAddress(selectedAddress.value) ? 0 : DEFAULT_SHIPPING_FEE;
});

const discountCents = computed(() => appliedCoupon.value?.discount_cents || 0);
const total = computed(() => {
  const discountedSubtotal = Math.max(0, cartStore.subtotal - discountCents.value);
  return discountedSubtotal + shippingFee.value;
});
const canPlaceOrder = computed(() => {
  if (loading.value || cartStore.loading || cartStore.items.length === 0)
    return false;
  if (fulfillmentMethod.value === "pickup") return true;
  return !!selectedAddressId.value;
});

const resetAddressForm = () => {
  newAddress.value = {
    label: "Nhà riêng",
    recipient_name: "",
    phone: "",
    line1: "",
    ward: "",
    district: "",
    province: "",
    is_default: true,
  };
  selectedProvinceCode.value = "";
};

const syncAddressSelection = () => {
  const province = provinceOptions.find(
    (item) => item.Code === selectedProvinceCode.value,
  );

  newAddress.value.province = province?.FullName || "";
  newAddress.value.district = String(newAddress.value.district || "").trim();
};

const handleProvinceChange = (event) => {
  selectedProvinceCode.value = event.target.value;
  newAddress.value.district = "";
  syncAddressSelection();
};

const submitNewAddress = async () => {
  syncAddressSelection();

  if (!newAddress.value.province || !newAddress.value.district) {
    toastStore.error(
      "Chọn Tỉnh/Thành phố và Quận/Huyện trước khi lưu địa chỉ.",
    );
    return;
  }

  if (!newAddress.value.ward) {
    newAddress.value.ward = newAddress.value.district;
  }

  savingAddress.value = true;
  try {
    const response = await axios.post("/api/v1/addresses", newAddress.value);
    const created = response.data;
    addresses.value = [
      created,
      ...addresses.value.filter((addr) => addr.id !== created.id),
    ];
    if (created.is_default) {
      addresses.value = addresses.value.map((addr) => ({
        ...addr,
        is_default: addr.id === created.id,
      }));
    }
    selectedAddressId.value = created.id;
    showAddressForm.value = false;
    resetAddressForm();
    toastStore.success("Đã thêm địa chỉ mới.");
  } catch (err) {
    toastStore.error(err.response?.data?.message || "Không thể thêm địa chỉ.");
  } finally {
    savingAddress.value = false;
  }
};

const applyCoupon = async () => {
  const code = String(couponCodeInput.value || "").trim();
  if (!code) {
    toastStore.error("Vui lòng nhập mã giảm giá.");
    return;
  }

  couponChecking.value = true;
  try {
    const response = await axios.get(
      `/api/v1/coupons/${encodeURIComponent(code)}/preview`,
      {
        params: {
          subtotal_cents: cartStore.subtotal,
        },
      },
    );

    if (!response.data?.valid) {
      toastStore.error("Mã giảm giá không hợp lệ.");
      return;
    }

    if (!response.data?.eligible) {
      const minSubtotal = Number(response.data?.min_subtotal_cents || 0);
      toastStore.error(
        `Đơn hàng chưa đạt mức tối thiểu ${formatPrice(minSubtotal)} để dùng mã này.`,
      );
      return;
    }

    appliedCoupon.value = {
      code,
      discount_cents: Number(response.data?.discount_cents || 0),
      min_subtotal_cents: Number(response.data?.min_subtotal_cents || 0),
    };
    couponCodeInput.value = code;
    localStorage.setItem(STORAGE_KEY, code);
    toastStore.success(`Đã áp dụng mã ${code}.`);
  } catch (err) {
    toastStore.error(err.response?.data?.message || "Không áp dụng được mã giảm giá.");
  } finally {
    couponChecking.value = false;
  }
};

const clearCoupon = () => {
  appliedCoupon.value = null;
  couponCodeInput.value = "";
  localStorage.removeItem(STORAGE_KEY);
};

const tryHydrateCouponFromState = async () => {
  const fromQuery = String(route.query?.coupon || "").trim();
  const fromStorage = String(localStorage.getItem(STORAGE_KEY) || "").trim();
  const fallbackCode = fromQuery || fromStorage;
  if (!fallbackCode) return;

  couponCodeInput.value = fallbackCode;
  await applyCoupon();
};

const placeOrder = async () => {
  if (!canPlaceOrder.value) return;

  loading.value = true;
  try {
    const response = await axios.post(
      "/api/v1/checkout",
      {
        fulfillment_method: fulfillmentMethod.value,
        shipping_address_id:
          fulfillmentMethod.value === "shipping"
            ? selectedAddressId.value
            : null,
        weight_grams: fulfillmentMethod.value === "shipping" ? 15000 : 0,
        tax_rate_basis_points: 0,
        coupon_code: appliedCoupon.value?.code || null,
        payment_method: "sepay_qr",
        customer_note: note.value,
      },
      {
        headers: { "X-Cart-Token": cartStore.cartToken },
      },
    );

    // Clear cart locally
    cartStore.items = [];
    cartStore.subtotal = 0;
    clearCoupon();

    router.push({
      name: "order-success",
      params: { id: response.data.order.id },
    });
  } catch (err) {
    const validationErrors = err.response?.data?.errors;
    const firstValidationMessage = validationErrors
      ? Object.values(validationErrors).flat()[0]
      : null;

    toastStore.error(
      firstValidationMessage ||
        err.response?.data?.message ||
        "Lỗi khi đặt hàng.",
    );
  } finally {
    loading.value = false;
  }
};

watch(
  () => cartStore.subtotal,
  (nextSubtotal) => {
    if (!appliedCoupon.value) return;
    if (nextSubtotal >= appliedCoupon.value.min_subtotal_cents) return;

    const oldCode = appliedCoupon.value.code;
    appliedCoupon.value = null;
    toastStore.error(`Mã ${oldCode} đã bị gỡ vì đơn hàng không còn đủ điều kiện.`);
  },
);

const formatPrice = (cents) => {
  return new Intl.NumberFormat("vi-VN", {
    style: "currency",
    currency: "VND",
  }).format(cents || 0);
};

const formatAddressLine = (addr) => {
  const parts = [addr?.line1, addr?.ward, addr?.district, addr?.province]
    .map((v) => String(v || "").trim())
    .filter((v) => v !== "");

  return [...new Set(parts)].join(", ");
};

onMounted(async () => {
  await cartStore.fetchCart();
  await tryHydrateCouponFromState();
  await fetchData();
});
</script>

<template>
  <div class="checkout-view animate-fade-in">
    <header style="margin-bottom: 40px">
      <h2 style="font-size: 2.5rem; font-weight: 800">
        Hoàn tất <span class="gradient-text">Đơn hàng</span>
      </h2>
      <p style="color: var(--text-secondary)">
        Chúng tôi sẽ chuẩn bị hàng ngay khi bạn xác nhận.
      </p>
    </header>

    <div class="checkout-layout">
      <div class="checkout-forms">
        <section class="checkout-section glass-panel">
          <div class="section-header">
            <Truck :size="20" color="var(--accent-primary)" />
            <h3>Hình thức nhận hàng</h3>
          </div>
          <div class="zone-list">
            <div
              class="zone-item"
              :class="{ active: fulfillmentMethod === 'shipping' }"
              @click="fulfillmentMethod = 'shipping'"
            >
              <div class="check-mark"><CheckCircle :size="16" /></div>
              <div>
                <strong>Giao hàng tận nơi</strong>
                <p>
                  Nội thành Hà Nội (theo quận) miễn phí, còn lại phí mặc định
                  30.000đ.
                </p>
              </div>
            </div>
            <div
              class="zone-item"
              :class="{ active: fulfillmentMethod === 'pickup' }"
              @click="fulfillmentMethod = 'pickup'"
            >
              <div class="check-mark"><CheckCircle :size="16" /></div>
              <div>
                <strong>Tự đến lấy</strong>
                <p>Nhận tại cửa hàng, không tính phí ship.</p>
              </div>
            </div>
          </div>
        </section>

        <section
          v-if="fulfillmentMethod === 'shipping'"
          class="checkout-section glass-panel"
        >
          <div class="section-header">
            <MapPin :size="20" color="var(--accent-primary)" />
            <h3>Địa chỉ nhận hàng</h3>
            <button
              class="link-btn"
              @click="showAddressForm = !showAddressForm"
            >
              {{ showAddressForm ? "Đóng" : "Thêm địa chỉ" }}
            </button>
          </div>
          <div v-if="showAddressForm" class="address-form">
            <div class="address-label-options">
              <button
                type="button"
                class="label-chip"
                :class="{ active: newAddress.label === 'Nhà riêng' }"
                @click="newAddress.label = 'Nhà riêng'"
              >
                Nhà riêng
              </button>
              <button
                type="button"
                class="label-chip"
                :class="{ active: newAddress.label === 'Công ty' }"
                @click="newAddress.label = 'Công ty'"
              >
                Công ty
              </button>
            </div>
            <input
              v-model="newAddress.recipient_name"
              type="text"
              placeholder="Người nhận"
            />
            <input
              v-model="newAddress.phone"
              type="text"
              placeholder="Số điện thoại"
            />
            <input
              v-model="newAddress.line1"
              type="text"
              placeholder="Số nhà, tên đường"
            />
            <select
              :value="selectedProvinceCode"
              @change="handleProvinceChange"
            >
              <option value="">Chọn Tỉnh/Thành phố</option>
              <option
                v-for="province in provinceOptions"
                :key="province.Code"
                :value="province.Code"
              >
                {{ province.FullName }}
              </option>
            </select>
            <select
              v-model="newAddress.district"
              :disabled="!selectedProvinceCode"
            >
              <option value="">Chọn Quận/Huyện</option>
              <option
                v-for="district in districtOptions"
                :key="district"
                :value="district"
              >
                {{ district }}
              </option>
            </select>
            <button
              type="button"
              class="default-toggle"
              :class="{ active: newAddress.is_default }"
              @click="newAddress.is_default = !newAddress.is_default"
            >
              <span class="toggle-knob">
                <CheckCircle v-if="newAddress.is_default" :size="14" />
              </span>
              <span class="toggle-copy">
                <strong>Đặt làm địa chỉ mặc định</strong>
                <small
                  >Địa chỉ này sẽ được chọn sẵn cho lần mua tiếp theo.</small
                >
              </span>
            </button>
            <button
              class="btn btn-primary"
              @click="submitNewAddress"
              :disabled="savingAddress"
            >
              {{ savingAddress ? "Đang lưu..." : "Lưu địa chỉ" }}
            </button>
          </div>
          <div class="address-list" v-if="addresses.length > 0">
            <div
              v-for="addr in addresses"
              :key="addr.id"
              class="address-item"
              :class="{ active: selectedAddressId === addr.id }"
              @click="selectedAddressId = addr.id"
            >
              <div class="check-mark"><CheckCircle :size="16" /></div>
              <div>
                <strong>{{ addr.recipient_name }}</strong> ({{ addr.phone }})
                <p>{{ formatAddressLine(addr) }}</p>
              </div>
            </div>
          </div>
          <div v-else class="empty-msg">
            Bạn chưa có địa chỉ nào. Hãy thêm địa chỉ trong hồ sơ.
          </div>
        </section>

        <section class="checkout-section glass-panel">
          <div class="section-header">
            <CreditCard :size="20" color="var(--accent-primary)" />
            <h3>Thanh toán & Ghi chú</h3>
          </div>
          <div style="padding: 20px">
            <div class="payment-method active">
              <CheckCircle :size="16" /> Chuyển khoản qua quét mã QR (SePay)
            </div>
            <textarea
              v-model="note"
              placeholder="Lưu ý cho người bán... (ví dụ: Giao giờ hành chính)"
              class="checkout-note"
            ></textarea>
          </div>
        </section>
      </div>

      <div class="order-summary-sidebar glass-panel">
        <h3 style="margin-bottom: 24px">Chi tiết đơn hàng</h3>
        <div class="coupon-box">
          <label for="coupon-input">Mã giảm giá</label>
          <div class="coupon-input-row">
            <input
              id="coupon-input"
              v-model="couponCodeInput"
              type="text"
              placeholder="Nhập mã giảm giá"
              :disabled="couponChecking"
            />
            <button
              class="btn btn-secondary coupon-apply-btn"
              @click="applyCoupon"
              :disabled="couponChecking || !couponCodeInput.trim()"
            >
              {{ couponChecking ? "..." : "Áp dụng" }}
            </button>
          </div>
          <div v-if="appliedCoupon" class="coupon-applied">
            <span>Đã áp dụng: <strong>{{ appliedCoupon.code }}</strong></span>
            <button type="button" class="coupon-clear-btn" @click="clearCoupon">
              Bỏ
            </button>
          </div>
        </div>

        <div class="summary-line">
          <span>Tiền hàng</span>
          <span>{{ formatPrice(cartStore.subtotal) }}</span>
        </div>
        <div v-if="discountCents > 0" class="summary-line discount-line">
          <span>Giảm giá</span>
          <span>- {{ formatPrice(discountCents) }}</span>
        </div>
        <div class="summary-line">
          <span>{{
            fulfillmentMethod === "pickup"
              ? "Nhận tại cửa hàng"
              : "Phí vận chuyển dự kiến"
          }}</span>
          <span>{{
            fulfillmentMethod === "pickup"
              ? formatPrice(0)
              : `+ ${formatPrice(shippingFee)}`
          }}</span>
        </div>

        <hr
          style="
            border: none;
            border-top: 1px solid var(--border-color);
            margin: 20px 0;
          "
        />

        <div class="summary-line total">
          <span>Tổng thanh toán</span>
          <span class="gradient-text">{{ formatPrice(total) }}</span>
        </div>

        <button
          @click="placeOrder"
          class="btn btn-primary w-100"
          style="margin-top: 32px; height: 56px; font-size: 1.1rem"
          :disabled="!canPlaceOrder"
        >
          {{ loading ? "Đang xử lý..." : "Xác nhận Đặt hàng" }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.checkout-view {
  padding: 60px 0;
}
.checkout-layout {
  display: flex;
  gap: 40px;
  align-items: flex-start;
}
.checkout-forms {
  flex-grow: 1;
}

.checkout-section {
  padding: 32px;
  margin-bottom: 24px;
}
.section-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 24px;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--border-color);
}
.section-header h3 {
  font-size: 1.25rem;
  font-weight: 700;
}

.address-item,
.zone-item {
  display: flex;
  gap: 16px;
  padding: 20px;
  border: 1px solid var(--border-color);
  border-radius: 12px;
  margin-bottom: 12px;
  cursor: pointer;
  transition: all 0.3s ease;
}

.address-item:hover,
.zone-item:hover {
  border-color: var(--accent-primary);
  background: rgba(255, 255, 255, 0.02);
}
.address-item.active,
.zone-item.active {
  border-color: var(--accent-primary);
  background: rgba(99, 102, 241, 0.05);
}

.check-mark {
  color: transparent;
  border: 2px solid var(--border-color);
  width: 24px;
  height: 24px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.active .check-mark {
  background: var(--accent-primary);
  border-color: var(--accent-primary);
  color: white;
}

.address-item p,
.zone-item p {
  font-size: 0.9rem;
  color: var(--text-secondary);
  margin-top: 4px;
}

.payment-method {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px;
  border-radius: 12px;
  background: rgba(99, 102, 241, 0.1);
  color: var(--accent-primary);
  font-weight: 600;
  border: 1px solid rgba(99, 102, 241, 0.2);
}

.checkout-note {
  width: 100%;
  height: 100px;
  padding: 16px;
  margin-top: 20px;
  background: var(--bg-secondary);
  border: 1px solid var(--border-color);
  border-radius: 12px;
  color: var(--text-primary);
  outline: none;
}

.order-summary-sidebar {
  width: 400px;
  padding: 32px;
  position: sticky;
  top: 100px;
}
.summary-line {
  display: flex;
  justify-content: space-between;
  margin-bottom: 16px;
}
.summary-line.total {
  font-size: 1.5rem;
  font-weight: 800;
}

.discount-line {
  color: #0f766e;
  font-weight: 600;
}

.coupon-box {
  padding: 14px;
  margin-bottom: 16px;
  border-radius: 12px;
  border: 1px solid var(--border-color);
  background: #fff;
}

.coupon-box label {
  display: block;
  font-size: 0.85rem;
  font-weight: 700;
  color: var(--text-secondary);
  margin-bottom: 8px;
}

.coupon-input-row {
  display: flex;
  gap: 8px;
}

.coupon-input-row input {
  flex: 1;
  min-width: 0;
  background: var(--bg-secondary);
  border: 1px solid var(--border-color);
  border-radius: 10px;
  padding: 10px 12px;
  color: var(--text-primary);
  outline: none;
}

.coupon-apply-btn {
  padding: 10px 12px;
  border-radius: 10px;
  white-space: nowrap;
}

.coupon-applied {
  margin-top: 10px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  color: var(--text-secondary);
  font-size: 0.9rem;
}

.coupon-clear-btn {
  border: none;
  background: none;
  color: #b91c1c;
  font-weight: 700;
  cursor: pointer;
}

.empty-msg {
  text-align: center;
  color: var(--text-secondary);
  padding: 40px;
}
.link-btn {
  margin-left: auto;
  background: none;
  border: none;
  color: var(--accent-primary);
  cursor: pointer;
  font-weight: 700;
}
.address-form {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
  margin-bottom: 20px;
}
.address-label-options {
  grid-column: 1 / -1;
  display: flex;
  gap: 10px;
}
.label-chip {
  border: 1px solid var(--border-color);
  background: var(--bg-secondary);
  color: var(--text-primary);
  border-radius: 999px;
  padding: 12px 16px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}
.label-chip.active {
  border-color: var(--accent-primary);
  background: rgba(99, 102, 241, 0.12);
  color: var(--accent-primary);
}
.address-form input,
.address-form select {
  width: 100%;
  background: var(--bg-secondary);
  border: 1px solid var(--border-color);
  border-radius: 12px;
  padding: 14px 16px;
  color: var(--text-primary);
  outline: none;
}
.address-form select:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.address-form > :nth-child(4) {
  grid-column: 1 / -1;
}
.default-toggle {
  grid-column: 1 / -1;
  display: flex;
  align-items: center;
  gap: 12px;
  border: 1px solid var(--border-color);
  background: #fff;
  border-radius: 14px;
  padding: 14px 16px;
  cursor: pointer;
  text-align: left;
  transition: all 0.2s ease;
}
.default-toggle.active {
  border-color: var(--accent-primary);
  background: rgba(99, 102, 241, 0.08);
}
.toggle-knob {
  width: 24px;
  height: 24px;
  border-radius: 999px;
  border: 2px solid var(--border-color);
  display: flex;
  align-items: center;
  justify-content: center;
  color: transparent;
  flex-shrink: 0;
  transition: all 0.2s ease;
}
.default-toggle.active .toggle-knob {
  border-color: var(--accent-primary);
  background: var(--accent-primary);
  color: #fff;
}
.toggle-copy {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.toggle-copy strong {
  color: var(--text-primary);
  font-size: 0.95rem;
}
.toggle-copy small {
  color: var(--text-secondary);
  font-size: 0.82rem;
}
.address-form .btn {
  grid-column: 1 / -1;
}
@media (max-width: 1024px) {
  .checkout-layout {
    flex-direction: column;
  }
  .order-summary-sidebar {
    width: 100%;
    position: static;
  }
}
@media (max-width: 720px) {
  .address-form {
    grid-template-columns: 1fr;
  }
}
.w-100 {
  width: 100%;
}
</style>

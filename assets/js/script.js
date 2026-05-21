// js/script.js - Versi Ringan Khusus Skripsi
document.addEventListener("DOMContentLoaded", function () {
  console.log("Monitor Listrik System Ready...");

  // 1. Fungsi Animasi Angka
  const animateValue = (id, newValue, decimal) => {
    const element = document.getElementById(id);
    if (!element) return;

    const startValue = 0; // Mulai dari 0 setiap refresh biar efeknya kelihatan
    const duration = 1500; // 1.5 detik animasi
    let startTimestamp = null;

    const step = (timestamp) => {
      if (!startTimestamp) startTimestamp = timestamp;
      const progress = Math.min((timestamp - startTimestamp) / duration, 1);
      const current = progress * (newValue - startValue) + startValue;

      // Tambahkan satuan di belakang angka
      let unit = id.includes("daya")
        ? " W"
        : id.includes("energi")
          ? " kWh"
          : " V";
      element.innerText = current.toFixed(decimal) + unit;

      if (progress < 1) {
        window.requestAnimationFrame(step);
      }
    };
    window.requestAnimationFrame(step);
  };

  // Jalankan animasi saat halaman dimuat (Data diambil dari innerText PHP)
  const dayaValue =
    parseFloat(document.getElementById("display-daya")?.innerText) || 0;
  const energiValue =
    parseFloat(document.getElementById("display-energi")?.innerText) || 0;
  const teganganValue =
    parseFloat(document.getElementById("display-tegangan")?.innerText) || 0;

  animateValue("display-daya", dayaValue, 1);
  animateValue("display-energi", energiValue, 3);
  animateValue("display-tegangan", teganganValue, 1);

  // 2. Notifikasi Browser
  if ("Notification" in window) {
    if (Notification.permission !== "granted") {
      Notification.requestPermission();
    }
  }

  // Fungsi Global untuk dipanggil jika ada anomali
  window.showEnergyAlert = (title, msg) => {
    if (Notification.permission === "granted") {
      new Notification(title, {
        body: msg,
        icon: "https://cdn-icons-png.flaticon.com/512/281/281195.png",
      });
    }
  };
});

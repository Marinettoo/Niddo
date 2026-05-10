<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
require_once '../config/db.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM devices WHERE id = ?");
$stmt->execute([$id]);
$device = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$device) die('Dispositivo no encontrado');

$token    = $device['token'];
$nombre   = $device['nombre'];
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base     = dirname(dirname($_SERVER['SCRIPT_NAME'])); // ej: /TFG/Niddo
$servidor = $protocol . '://' . $_SERVER['HTTP_HOST'] . $base . '/api/backup.php';

$script = <<<'PYTHON'
import os, sys, hashlib, uuid, json, subprocess, urllib.request, threading
import tkinter as tk
from tkinter import filedialog, scrolledtext

TOKEN    = "__TOKEN__"
SERVIDOR = "__SERVIDOR__"
NOMBRE   = "__NOMBRE__"
TAREA    = "NiddoBackup_" + NOMBRE.replace(" ", "_")
CONFIG   = os.path.join(os.environ.get('APPDATA', ''), 'Niddo', NOMBRE + '.json')

# ---- Config ----

def cargar_config():
    if os.path.exists(CONFIG):
        with open(CONFIG) as f:
            return json.load(f)
    return {'carpetas': [], 'intervalo': 24}

def guardar_config(carpetas, intervalo):
    os.makedirs(os.path.dirname(CONFIG), exist_ok=True)
    with open(CONFIG, 'w') as f:
        json.dump({'carpetas': carpetas, 'intervalo': intervalo}, f)

# ---- Backup ----

def hash_archivo(ruta):
    h = hashlib.sha256()
    with open(ruta, 'rb') as f:
        for chunk in iter(lambda: f.read(8192), b''):
            h.update(chunk)
    return h.hexdigest()

def subir_archivo(ruta):
    nombre   = os.path.basename(ruta)
    hash_sha = hash_archivo(ruta)
    boundary = uuid.uuid4().hex
    with open(ruta, 'rb') as f:
        contenido = f.read()
    nl  = b'\r\n'
    sep = ('--' + boundary).encode()
    cuerpo = (
        sep + nl +
        b'Content-Disposition: form-data; name="token"' + nl + nl +
        TOKEN.encode() + nl +
        sep + nl +
        b'Content-Disposition: form-data; name="hash"' + nl + nl +
        hash_sha.encode() + nl +
        sep + nl +
        ('Content-Disposition: form-data; name="archivo"; filename="' + nombre + '"').encode() + nl +
        b'Content-Type: application/octet-stream' + nl + nl +
        contenido + nl +
        sep + b'--' + nl
    )
    req = urllib.request.Request(SERVIDOR, data=cuerpo,
        headers={'Content-Type': f'multipart/form-data; boundary={boundary}'})
    with urllib.request.urlopen(req, timeout=30) as r:
        return r.read().decode()

def hacer_backup(carpetas, log_fn=None):
    total = errores = 0
    for carpeta in carpetas:
        if not os.path.exists(carpeta):
            if log_fn: log_fn(f'[omitida] {carpeta}')
            continue
        for raiz, dirs, archivos in os.walk(carpeta):
            for nombre in archivos:
                ruta = os.path.join(raiz, nombre)
                try:
                    subir_archivo(ruta)
                    if log_fn: log_fn(f'[ok] {ruta}')
                    total += 1
                except Exception as e:
                    if log_fn: log_fn(f'[error] {ruta} — {e}')
                    errores += 1
    return total, errores

# ---- Modo automatico (sin GUI) ----

if '--auto' in sys.argv:
    cfg = cargar_config()
    hacer_backup(cfg.get('carpetas', []))
    sys.exit(0)

# ---- GUI ----

class Agente:
    def __init__(self, root):
        self.root = root
        self.root.title(f'Niddo — {NOMBRE}')
        self.root.configure(bg='#111')
        self.root.resizable(False, False)
        self.cfg = cargar_config()
        self.construir()

    def construir(self):
        tk.Label(self.root, text='NIDDO  ·  agente de backup', bg='#111',
                 fg='#7eb8f7', font=('Courier', 13)).pack(pady=(16, 4))
        tk.Label(self.root, text=NOMBRE, bg='#111', fg='#333',
                 font=('Courier', 10)).pack()
        tk.Label(self.root, text=f'→ {SERVIDOR}', bg='#111', fg='#2a2a2a',
                 font=('Courier', 8)).pack()

        # --- Discos detectados ---
        tk.Label(self.root, text='Discos disponibles', bg='#111', fg='#555',
                 font=('Courier', 9)).pack(pady=(12, 2))

        disco_frame = tk.Frame(self.root, bg='#111')
        disco_frame.pack()
        self.vars_disco = {}
        col = 0
        for d in 'ABCDEFGHIJKLMNOPQRSTUVWXYZ':
            ruta = f'{d}:\\'
            if os.path.exists(ruta):
                var = tk.BooleanVar(value=ruta in self.cfg['carpetas'])
                self.vars_disco[ruta] = var
                tk.Checkbutton(disco_frame, text=ruta, variable=var,
                               bg='#111', fg='#aaa', selectcolor='#1a1a1a',
                               activebackground='#111', activeforeground='#ccc',
                               font=('Courier', 10)).grid(row=0, column=col, padx=6)
                col += 1

        # --- Carpetas adicionales ---
        tk.Label(self.root, text='Carpetas adicionales', bg='#111', fg='#555',
                 font=('Courier', 9)).pack(pady=(10, 2))

        frame = tk.Frame(self.root, bg='#111')
        frame.pack(padx=16, fill='both')
        self.lista = tk.Listbox(frame, bg='#1a1a1a', fg='#ccc', selectbackground='#2a3a4a',
                                font=('Courier', 10), width=55, height=5, bd=0,
                                highlightthickness=1, highlightbackground='#2a2a2a')
        self.lista.pack(side='left', fill='both', expand=True)
        scroll = tk.Scrollbar(frame, command=self.lista.yview, bg='#1a1a1a')
        scroll.pack(side='right', fill='y')
        self.lista.config(yscrollcommand=scroll.set)

        carpetas_extra = [c for c in self.cfg['carpetas'] if not c.endswith(':\\')]
        for c in carpetas_extra:
            self.lista.insert(tk.END, c)

        bframe = tk.Frame(self.root, bg='#111')
        bframe.pack(pady=4)
        btn = {'bg': '#1a1a1a', 'fg': '#aaa', 'font': ('Courier', 10), 'bd': 0,
               'padx': 14, 'pady': 5, 'cursor': 'hand2',
               'activebackground': '#222', 'activeforeground': '#fff'}
        tk.Button(bframe, text='+ Carpeta', command=self.añadir, **btn).pack(side='left', padx=4)
        tk.Button(bframe, text='− Eliminar', command=self.eliminar, **btn).pack(side='left', padx=4)

        # --- Intervalo y servicio ---
        sframe = tk.Frame(self.root, bg='#111')
        sframe.pack(pady=6)
        tk.Label(sframe, text='Repetir cada', bg='#111', fg='#555',
                 font=('Courier', 10)).pack(side='left', padx=(0, 6))
        self.intervalo = tk.Spinbox(sframe, from_=1, to=168, width=4,
                                    bg='#1a1a1a', fg='#ccc', font=('Courier', 10),
                                    buttonbackground='#2a2a2a', bd=0)
        self.intervalo.delete(0, 'end')
        self.intervalo.insert(0, str(self.cfg.get('intervalo', 24)))
        self.intervalo.pack(side='left')
        tk.Label(sframe, text='horas', bg='#111', fg='#555',
                 font=('Courier', 10)).pack(side='left', padx=6)
        tk.Button(sframe, text='Activar servicio', command=self.activar_servicio,
                  bg='#1a1a1a', fg='#5a9', font=('Courier', 10), bd=0,
                  padx=12, pady=5, cursor='hand2',
                  activebackground='#222', activeforeground='#7eb8f7').pack(side='left', padx=8)

        # --- Log ---
        self.log = scrolledtext.ScrolledText(self.root, bg='#0d0d0d', fg='#5a9',
                                             font=('Courier', 9), width=60, height=8,
                                             bd=0, state='disabled',
                                             highlightthickness=1, highlightbackground='#1a1a1a')
        self.log.pack(padx=16, pady=10)

        self.btn = tk.Button(self.root, text='INICIAR BACKUP AHORA', command=self.iniciar,
                             bg='#7eb8f7', fg='#111', font=('Courier', 11, 'bold'),
                             bd=0, padx=20, pady=10, cursor='hand2',
                             activebackground='#aad0ff')
        self.btn.pack(pady=(0, 16))

    def get_carpetas(self):
        discos = [ruta for ruta, var in self.vars_disco.items() if var.get()]
        extras = list(self.lista.get(0, 'end'))
        return discos + extras

    def añadir(self):
        c = filedialog.askdirectory()
        if c:
            self.lista.insert(tk.END, c)
ound
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\9c141a90ca37eedcc7a8a9e53ad7ff70.cnmt.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\9c98d3cc63fca1a9f57ad0fd714ffc03.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\9cc0a36997c66d681bb8e315d7773dc2.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\9cdd4b7c5a9ca605240f3364f10d6597.cnmt.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\9f0888d4b0ec90243f7b695bf9eeb4e8.cnmt.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\9f3949ad0c404f06e5625113e35f7317.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\a3bdf2ed9edf7c8aa47442a4e4939640.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\a3d48e1e481abdd3790237c484290825.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\a52b7b442af0d6ddbc3d9a867159ba9f.cnmt.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\a5642b953d971f20233fce56084a7325.cnmt.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\a6a7f7b19b0f83c8f0d3c1129585d315.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\a73f2739323b796fe9797712d4f5abf1.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\a847ec77e431ed23ac687b118620e755.cnmt.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\aa7780e9f214bc442a24e5119896169d.cnmt.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\aa94ee1ffd362b9e2a7f677788d52dbb.cnmt.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\aa99b4f3e254da6b0340e412225a164d.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\ac8f44c849c89c33d7d7e7ab005653f9.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\aebb89210ad7b90ee5700ccf9ae2dcc7.cnmt.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\afc78068f5165699a8c9db2bef43dd1d.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\b2a131f893eb4d3e8387f5e983ec53bf.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\b2bb824bc0380303442e6dc54ba5ec13.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\b38a42208b8d8a90f29e6905474807a0.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\b3a6f8ff4687ea4e26f5e46bcd4782ed.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\b5d30515341fdd95471a4f938589afb7.cnmt.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\b60b4cda5d9f0f538a19b5178039f897.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\b89bf8694af3889121cab43569890fea.cnmt.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\b8efbb30f45d8d6b3569ff504c94322f.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\b9152056012c0d292641980788413803.cnmt.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\baec9bbd447d0704d302d02c0c16e3be.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\bb7978a5408d45c2285c63a4ad8caacb.cnmt.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\bd7bb589f75082bbf428c9154c059f52.cnmt.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\be3340e963424d805c8310b06e56c457.cnmt.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\bfab8caa278e456f9fc5e1f5fd734feb.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\c119c6d39a859929bb112746e80e4b66.cnmt.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\c4ac9614def353a73dd99c05d4dd8d6a.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\c58381611258f72aa5d28a55a338eee6.cnmt.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\c5d032046782d4bd6fc4b5c012a4e110.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\c662174a4e815559883c7b8559565b10.cnmt.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\c726fd5e0c8889645e6c46227a0d18fb.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\c7768547988b590c7f4c5acce9245e34.cnmt.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\c7dd0a691a3608a0553d0dfe2e1d0f56.cnmt.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\c94b4c25e1e50f84905df909bee9142e.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\c9c70ebe535426439852515598498df3.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\ca66270be492a16bab1d779645965bc8.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\cb52f5f38ddd5088de28af57a555ccef.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\cd6d6e96f36f7cf99f85ddae181c9354.cnmt.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\d16d51fbd10f3f062fcf4ea0c4ae97b8.cnmt.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\d374a1c5c5d6f3d9343c319155624a33.cnmt.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\d43802ae368a813b7f94d4c142ed5785.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\d4841eb33c6454e7e8b39744a8735d40.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\d6dce3a8bd63b2c182b9991fdf40fa6e.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\d8522abf1bfabdc5e840db37b24eaaaf.cnmt.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\dab727965af2a9f859cabfd63160a176.cnmt.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\dd9804ef20856c4d44a32bf262aa1219.cnmt.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\ddcc505cc8f870e3736f0eb933c73579.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\e04900ebc99d91d15b6825c131dccd7c.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\e31968804b40c09b854f3ab3dcaf719f.cnmt.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\e51336e1ea49dc368e4bd633dee01498.cnmt.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\e58def6ac7243adc3ab9b5da7cadb06c.cnmt.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\e5eee9cca780702db1a9cbd02b9b8e34.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\e8bccd3f3f93436a17f59198f8422b4b.cnmt.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\eb54adba82d1f509783eda8ffaac762f.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\ecba4c3061d8ed5e259b18060a87b6d8.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\ece1d88b4660c817d56183a515372a48.cnmt.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\ed7300fcfb6ed9dc5db155d2117a5691.cnmt.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\eda5372784442ce320444ac11d38b6cc.cnmt.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\f0fc8eb4db64b4d28f1e5a359781d524.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\f1e2abfe258ad114f497f7dcf13cc5fc.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\f24b4fac09fff802943401623c8feaf2.cnmt.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\f30af99483dcf9afd79ad8aab217b2d6.cnmt.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\f334d1e7c18b0ee01a0da26685a651c5.cnmt.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\f55d036f5c8810a5ed457b0d3b402e00.cnmt.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\f5718a5ad728bb81fe664e11aa41090e.cnmt.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\f643331813a2f61c4795f83aebbb5cd3.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\f8e894af1253c7b2a760f52599bdbc99.cnmt.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\fa6564e7d14e17f15cde2b4d323f80cb.cnmt.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\fb17499eac820702fbe13cf9fdbe7b98.cnmt.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\fd78a9e407f957e298241c62bbac9b28.cnmt.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\fe3c3e687a54d43b1a31469f9d6c6172.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\fe4e506faeea1039f5099e26928190a1.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\fe9a867525e1c4249761034ee7a03e28.cnmt.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Firmware.22.1.0\ffd294caefb1189e68e64fb6afd51228.cnmt.nca — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Keys-22.1.0\prod.keys — HTTP Error 404: Not Found
[error] C:/Users/jesus/Desktop/ryujinx\Keys-22.1.0\title.keys — HTTP Error 404: Not Found

    def eliminar(self):
        for i in reversed(self.lista.curselection()):
            self.lista.delete(i)

    def log_write(self, texto):
        self.log.config(state='normal')
        self.log.insert('end', texto + '\n')
        self.log.see('end')
        self.log.config(state='disabled')

    def activar_servicio(self):
        carpetas  = self.get_carpetas()
        intervalo = int(self.intervalo.get())
        guardar_config(carpetas, intervalo)
        exe = sys.executable if not getattr(sys, 'frozen', False) else sys.executable
        cmd = f'schtasks /create /tn "{TAREA}" /tr \\"{exe}\\" --auto /sc hourly /mo {intervalo} /f'
        subprocess.run(cmd, shell=True)
        self.log_write(f'[servicio] Tarea registrada: cada {intervalo}h')

    def iniciar(self):
        carpetas = self.get_carpetas()
        if not carpetas:
            return
        guardar_config(carpetas, int(self.intervalo.get()))
        self.btn.config(state='disabled', text='Procesando...')
        threading.Thread(target=self.run_backup, args=(carpetas,), daemon=True).start()

    def run_backup(self, carpetas):
        total, errores = hacer_backup(carpetas, self.log_write)
        self.log_write(f'\n--- {total} subidos, {errores} errores ---')
        self.btn.config(state='normal', text='INICIAR BACKUP AHORA')

root = tk.Tk()
Agente(root)
root.mainloop()
PYTHON;

$script = str_replace(
    ['__TOKEN__', '__SERVIDOR__', '__NOMBRE__'],
    [$token, $servidor, $nombre],
    $script
);

if (!isset($_GET['build'])) {
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Niddo — Generando agente</title>
    <link rel="stylesheet" href="style.css">
    <meta http-equiv="refresh" content="1;url=generar_agente.php?id=<?= $id ?>&build=1">
</head>
<body>
<header><div class="logo">NID<span>DO</span></div></header>
<div class="contenido" style="padding-top:60px; text-align:center">
    <p style="color:#555; font-size:12px; text-transform:uppercase; letter-spacing:2px">Generando ejecutable...</p>
    <p style="color:#333; margin-top:12px; font-size:11px">Esto puede tardar hasta un minuto</p>
</div>
</body>
</html>
<?php
    exit;
}

set_time_limit(300);
$tmpdir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'niddo_build_' . $id;
if (!is_dir($tmpdir)) mkdir($tmpdir, 0755, true);

file_put_contents($tmpdir . DIRECTORY_SEPARATOR . 'agente.py', $script);

exec('python -m PyInstaller --version 2>&1', $out, $code);
if ($code !== 0) exec('pip install pyinstaller 2>&1');

$dist = $tmpdir . DIRECTORY_SEPARATOR . 'dist';
$cmd  = "python -m PyInstaller --onefile --noconsole --distpath \"{$dist}\" --workpath \"{$tmpdir}\\build\" --specpath \"{$tmpdir}\" \"{$tmpdir}\\agente.py\" 2>&1";
exec($cmd, $output, $ret);

$exe = $dist . DIRECTORY_SEPARATOR . 'agente.exe';
if (!file_exists($exe)) {
    echo '<pre>' . implode("\n", array_map('htmlspecialchars', $output)) . '</pre>';
    exit;
}

$nombre_exe = 'niddo_agente_' . preg_replace('/[^a-z0-9]/i', '_', $nombre) . '.exe';
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $nombre_exe . '"');
header('Content-Length: ' . filesize($exe));
readfile($exe);

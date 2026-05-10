<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
require_once '../config/db.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM devices WHERE id = ?");
$stmt->execute([$id]);
$device = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$device) die('Dispositivo no encontrado');

$token    = $device['token'];
$nombre   = $device['nombre'];
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base     = dirname(dirname($_SERVER['SCRIPT_NAME']));
$servidor = $protocol . '://' . $_SERVER['HTTP_HOST'] . $base . '/api/backup.php';

$script = <<<'PYTHON'
import os, sys, hashlib, uuid, subprocess, threading, json, urllib.request
import tkinter as tk
from tkinter import filedialog, scrolledtext

TOKEN    = "__TOKEN__"
SERVIDOR = "__SERVIDOR__"
NOMBRE   = "__NOMBRE__"
TAREA    = "NiddoBackup_" + NOMBRE.replace(" ", "_")
CONFIG   = os.path.join(os.environ.get('APPDATA', ''), 'Niddo', NOMBRE + '.json')

def cargar_config():
    if os.path.exists(CONFIG):
        with open(CONFIG) as f:
            return json.load(f)
    return {'carpetas': [], 'intervalo': 24}

def guardar_config(carpetas, intervalo):
    os.makedirs(os.path.dirname(CONFIG), exist_ok=True)
    with open(CONFIG, 'w') as f:
        json.dump({'carpetas': carpetas, 'intervalo': intervalo}, f)

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

if '--auto' in sys.argv:
    cfg = cargar_config()
    hacer_backup(cfg.get('carpetas', []))
    sys.exit(0)

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
        tk.Label(self.root, text=NOMBRE, bg='#111', fg='#888',
                 font=('Courier', 10)).pack()
        tk.Label(self.root, text=f'-> {SERVIDOR}', bg='#111', fg='#333',
                 font=('Courier', 8)).pack()

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
        for c in [c for c in self.cfg['carpetas'] if not c.endswith(':\\')]:
            self.lista.insert(tk.END, c)

        bframe = tk.Frame(self.root, bg='#111')
        bframe.pack(pady=4)
        btn = {'bg': '#1a1a1a', 'fg': '#aaa', 'font': ('Courier', 10), 'bd': 0,
               'padx': 14, 'pady': 5, 'cursor': 'hand2',
               'activebackground': '#222', 'activeforeground': '#fff'}
        tk.Button(bframe, text='+ Carpeta', command=self.anyadir, **btn).pack(side='left', padx=4)
        tk.Button(bframe, text='- Eliminar', command=self.eliminar, **btn).pack(side='left', padx=4)

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
        return [r for r, v in self.vars_disco.items() if v.get()] + list(self.lista.get(0, 'end'))

    def anyadir(self):
        c = filedialog.askdirectory()
        if c:
            self.lista.insert(tk.END, c)

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
        exe = sys.executable
        cmd = f'schtasks /create /tn "{TAREA}" /tr "{exe} {__file__} --auto" /sc hourly /mo {intervalo} /f'
        subprocess.run(cmd, shell=True)
        self.log_write(f'[servicio] Tarea creada: cada {intervalo}h')

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

$nombre_archivo = 'niddo_' . preg_replace('/[^a-z0-9]/i', '_', $nombre) . '.py';
header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');
header('Content-Length: ' . strlen($script));
echo $script;

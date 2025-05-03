// Tema geçişi ve localStorage
const themeToggle=document.getElementById('themeToggle');function setTheme(e){document.body.setAttribute('data-theme',e),localStorage.setItem('theme',e),themeToggle.textContent='dark'===e?'☀️':'🌙'}const savedTheme=localStorage.getItem('theme')||'light';setTheme(savedTheme),themeToggle.addEventListener('click',function(){setTheme('dark'===document.body.getAttribute('data-theme')?'light':'dark')});const uploadForm=document.getElementById('uploadForm');uploadForm&&uploadForm.addEventListener('submit',function(e){uploadForm.checkValidity()?(document.getElementById('uploadSpinner').style.display='block'):e.preventDefault(),uploadForm.classList.add('was-validated')});function showToast(e,t){var n=document.getElementById('mainToast'),o=document.getElementById('toastMsg');o.textContent=e,n.classList.remove('text-bg-primary','text-bg-success','text-bg-danger'),'success'===t?n.classList.add('text-bg-success'):'danger'===t?n.classList.add('text-bg-danger'):n.classList.add('text-bg-primary');var a=new bootstrap.Toast(n,{delay:3500});a.show()}

// Infinite scroll dosya yükleme
let currentPage=1,loading=false,hasMore=true;const tableBody=document.querySelector('table tbody'),loader=document.createElement('tr');loader.innerHTML='<td colspan="7" class="text-center py-3"><span class="spinner-border text-primary"></span> Yükleniyor...</td>';const noMore=document.createElement('tr');noMore.innerHTML='<td colspan="7" class="text-center py-3 text-muted">Daha fazla dosya yok.</td>';
function loadFiles(reset=false){
    if(loading||!hasMore)return;
    loading=true;
    if(reset){currentPage=1;hasMore=true;tableBody.innerHTML='';}
    tableBody.appendChild(loader);
    const params=new URLSearchParams(window.location.search);
    const search=document.querySelector('input[name="search"]')?.value||'';
    const category=document.querySelector('select[name="category"]')?.value||'';
    fetch(`files_api.php?page=${currentPage}&search=${encodeURIComponent(search)}&category=${encodeURIComponent(category)}`)
    .then(r=>r.json()).then(data=>{
        tableBody.removeChild(loader);
        if(data.files&&data.files.length){
            data.files.forEach(row=>{
                let ext=row.ext;
                let preview='';
                const video_exts=['mp4','webm','ogg','mov','mkv','avi','flv','mpeg'];
                const image_exts=['jpg','jpeg','png','gif','bmp','webp','svg'];
                const text_exts=['txt','csv','log','md'];
                const pdf_exts=['pdf'];
                const archive_exts=['zip','rar','7z','tar','gz'];
                if(video_exts.includes(ext))
                    preview+='<br><video src="uploads/'+row.user_id+'/'+row.filename+'" controls loading="lazy" style="max-width:320px; max-height:180px; margin-top:4px; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.08);"></video>';
                else if(image_exts.includes(ext))
                    preview+='<br><img src="uploads/'+row.user_id+'/'+row.filename+'" loading="lazy" style="max-width:120px; max-height:80px; margin-top:4px; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.08);" alt="Önizleme">';
                else if(text_exts.includes(ext))
                    preview+='<br><span class="text-muted" style="font-size:12px;">Önizleme yok</span>';
                else if(pdf_exts.includes(ext))
                    preview+='<br><img src="https://cdn.jsdelivr.net/gh/walkxcode/dashboard-icons/svg/pdf.svg" alt="PDF" style="width:24px;vertical-align:middle;">';
                else if(archive_exts.includes(ext))
                    preview+='<br><span class="text-muted" style="font-size:12px;">Önizleme yok</span>';
                tableBody.insertAdjacentHTML('beforeend',
                    `<tr>
                        <td>${row.original_name}${preview}</td>
                        <td>${row.category||''}</td>
                        <td>${(row.file_size/1024).toFixed(2)} KB</td>
                        <td>${row.upload_date}</td>
                        <td>${row.download_count}</td>
                        <td><a href="?download=${row.id}" class="btn btn-success btn-sm me-2">İndir</a> <a href="?delete=${row.id}" class="btn btn-danger btn-sm" onclick="return confirm('Dosyayı silmek istediğinize emin misiniz?');">Sil</a></td>
                    </tr>`
                );
            });
            currentPage++;
            hasMore=data.has_more;
            if(!hasMore)tableBody.appendChild(noMore);
        }else{
            hasMore=false;
            tableBody.appendChild(noMore);
        }
        loading=false;
    });
}
window.addEventListener('scroll',()=>{
    if(!hasMore||loading)return;
    const scrollY=window.scrollY||window.pageYOffset;
    const winH=window.innerHeight;
    const docH=document.body.offsetHeight;
    if(scrollY+winH+200>docH){
        loadFiles();
    }
});
// Arama veya kategori değişirse tabloyu sıfırla ve baştan yükle
const searchInput=document.querySelector('input[name="search"]');
const categorySelect=document.querySelector('select[name="category"]');
if(searchInput)searchInput.addEventListener('input',()=>{loadFiles(true);});
if(categorySelect)categorySelect.addEventListener('change',()=>{loadFiles(true);});
// İlk yüklemede başlat
if(tableBody)loadFiles(true); 
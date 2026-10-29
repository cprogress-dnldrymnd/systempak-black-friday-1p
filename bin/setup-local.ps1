$ErrorActionPreference = 'Stop'

$mysql  = "C:\Users\donra\AppData\Local\Programs\Local\resources\extraResources\lightning-services\mysql-8.0.35+4\bin\win64\bin\mysql.exe"
$php    = "$env:USERPROFILE\AppData\Roaming\Local\lightning-services\php-8.2.27+1\bin\win64\php.exe"
$helper = "C:\Users\donra\Local Sites\systempak\app\public\wp-content\plugins\systempak-black-friday-1p\bin\setup-local.php"
$tmp    = "$env:TEMP\spbf-setup"
New-Item -ItemType Directory -Force -Path $tmp | Out-Null

function Invoke-Mysql([string]$sql) {
    $prev = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    $out = & $mysql --user=root --password=root --host=127.0.0.1 --port=10210 --database=local --batch --raw --skip-column-names --execute=$sql 2>$null
    $ErrorActionPreference = $prev
    return $out
}

function Get-OptionHex([string]$name) {
    (Invoke-Mysql "SELECT HEX(option_value) FROM baz2jz_options WHERE option_name='$name' LIMIT 1;").ToString().Trim()
}

function Set-OptionHex([string]$name, [string]$hex) {
    $exists = (Invoke-Mysql "SELECT COUNT(*) FROM baz2jz_options WHERE option_name='$name';").ToString().Trim()
    if ($exists -eq '0') {
        Invoke-Mysql "INSERT INTO baz2jz_options (option_name, option_value, autoload) VALUES ('$name', UNHEX('$hex'), 'yes');" | Out-Null
    } else {
        Invoke-Mysql "UPDATE baz2jz_options SET option_value=UNHEX('$hex') WHERE option_name='$name';" | Out-Null
    }
}

Write-Host '=== 1) Activate plugin ==='
$activeHex = Get-OptionHex 'active_plugins'
Set-Content -Path "$tmp\active_in.hex" -Value $activeHex -NoNewline -Encoding ascii
& $php $helper activate-plugin "$tmp\active_in.hex" "$tmp\active_out.hex"
$newActiveHex = [System.IO.File]::ReadAllText("$tmp\active_out.hex").Trim()
Set-OptionHex 'active_plugins' $newActiveHex
$verifyHex = Get-OptionHex 'active_plugins'
Set-Content -Path "$tmp\active_verify.hex" -Value $verifyHex -NoNewline -Encoding ascii
& $php $helper check-plugin "$tmp\active_verify.hex"

Write-Host '=== 2) Ensure product category ==='
$termTax = Invoke-Mysql @"
SELECT t.term_id
FROM baz2jz_terms t
INNER JOIN baz2jz_term_taxonomy tt ON t.term_id = tt.term_id
WHERE tt.taxonomy='product_cat' AND t.slug='black-friday-1p-offer'
LIMIT 1;
"@
$termTax = if ($termTax) { $termTax.ToString().Trim() } else { '' }

if (-not $termTax) {
    Invoke-Mysql "INSERT INTO baz2jz_terms (name, slug, term_group) VALUES ('Black Friday 1p Offer', 'black-friday-1p-offer', 0);" | Out-Null
    $termId = (Invoke-Mysql "SELECT term_id FROM baz2jz_terms WHERE slug='black-friday-1p-offer' ORDER BY term_id DESC LIMIT 1;").ToString().Trim()
    Invoke-Mysql "INSERT INTO baz2jz_term_taxonomy (term_id, taxonomy, description, parent, count) VALUES ($termId, 'product_cat', 'Products included in the SystemPAK Black Friday 1p a Box promotion.', 0, 0);" | Out-Null
    Write-Host "Created category term_id=$termId"
} else {
    Write-Host "Category already exists term_id=$termTax"
}

Write-Host '=== 3) Create / reuse collection page ==='
$pageId = (Invoke-Mysql "SELECT ID FROM baz2jz_posts WHERE post_type='page' AND post_name='black-friday-1p-a-box' AND post_status IN ('publish','draft') ORDER BY ID DESC LIMIT 1;").ToString().Trim()

& $php $helper page-content "$tmp\page_content.hex"
$pageHex = [System.IO.File]::ReadAllText("$tmp\page_content.hex").Trim()
$now = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
$title = 'Black Friday 1p a Box'
$guid = "http://systempak.local/?page_id="

if (-not $pageId) {
    Invoke-Mysql @"
INSERT INTO baz2jz_posts
(post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt, post_status, comment_status, ping_status, post_password, post_name, to_ping, pinged, post_modified, post_modified_gmt, post_content_filtered, post_parent, guid, menu_order, post_type, post_mime_type, comment_count)
VALUES
(1, '$now', '$now', UNHEX('$pageHex'), '$title', '', 'publish', 'closed', 'closed', '', 'black-friday-1p-a-box', '', '', '$now', '$now', '', 0, '', 0, 'page', '', 0);
"@ | Out-Null
    $pageId = (Invoke-Mysql "SELECT ID FROM baz2jz_posts WHERE post_name='black-friday-1p-a-box' ORDER BY ID DESC LIMIT 1;").ToString().Trim()
    Invoke-Mysql "UPDATE baz2jz_posts SET guid='http://systempak.local/?page_id=$pageId' WHERE ID=$pageId;" | Out-Null
    Write-Host "Created page ID=$pageId"
} else {
    Invoke-Mysql "UPDATE baz2jz_posts SET post_content=UNHEX('$pageHex'), post_status='publish', post_title='$title', post_modified='$now', post_modified_gmt='$now' WHERE ID=$pageId;" | Out-Null
    Write-Host "Updated existing page ID=$pageId"
}

Write-Host '=== 4) Save plugin settings (admin_only) ==='
& $php $helper settings $pageId "$tmp\settings.hex"
$settingsHex = [System.IO.File]::ReadAllText("$tmp\settings.hex").Trim()
Set-OptionHex 'spbf_settings' $settingsHex
Write-Host "Settings saved with collection_page_id=$pageId"

Write-Host '=== 5) Homepage banner note ==='
$front = (Invoke-Mysql "SELECT option_value FROM baz2jz_options WHERE option_name='page_on_front' LIMIT 1;").ToString().Trim()
$show  = (Invoke-Mysql "SELECT option_value FROM baz2jz_options WHERE option_name='show_on_front' LIMIT 1;").ToString().Trim()
Write-Host "show_on_front=$show page_on_front=$front"

Write-Host '=== DONE ==='
Write-Host "Collection URL: http://systempak.local/black-friday-1p-a-box/"
Write-Host "Settings: WooCommerce > Black Friday 1p (visibility=admin_only)"

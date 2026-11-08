#!/bin/bash

set -e

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
VIMRC="${HOME}/.vimrc"
BACKUP_DIR="${HOME}/.vim/backups"

# Resolve the global binary location
if [ -x "/user/bin/pncagent" ]; then
    BINARY_PATH="/user/bin/pncagent"
elif [ -x "/usr/bin/pncagent" ]; then
    BINARY_PATH="/usr/bin/pncagent"
elif command -v pncagent >/dev/null 2>&1; then
    BINARY_PATH="$(command -v pncagent)"
else
    BINARY_PATH="/user/bin/pncagent"
fi

echo "=================================================="
echo "Configuring Vim for PncAgent CLI"
echo "Repository Root: ${REPO_ROOT}"
echo "Binary Location: ${BINARY_PATH}"
echo "=================================================="

# 1. Back up ~/.vimrc if it exists
if [ -f "${VIMRC}" ]; then
    mkdir -p "${BACKUP_DIR}"
    BACKUP_FILE="${BACKUP_DIR}/vimrc.bak.$(date +%Y%m%d_%H%M%S)"
    cp "${VIMRC}" "${BACKUP_FILE}"
    echo "Backed up existing ~/.vimrc to: ${BACKUP_FILE}"
fi

# 2. Ensure vim-plug is installed
PLUG_FILE="${HOME}/.vim/autoload/plug.vim"
if [ ! -f "${PLUG_FILE}" ]; then
    echo "Installing vim-plug..."
    curl -fLo "${PLUG_FILE}" --create-dirs \
        https://raw.githubusercontent.com/junegunn/vim-plug/master/plug.vim
fi

# 3. Write configured ~/.vimrc with dynamic repository path, plugins, and keybindings
cat << VIMRCEOF > "${VIMRC}"
" --- Automatic Plugin Management ---
let data_dir = has('nvim') ? stdpath('data') . '/site' : '~/.vim'
if empty(glob(data_dir . '/autoload/plug.vim'))
  silent execute '!curl -fLo '.data_dir.'/autoload/plug.vim --create-dirs https://raw.githubusercontent.com/junegunn/vim-plug/master/plug.vim'
  autocmd VimEnter * PlugInstall --sync | source \$MYVIMRC
endif

call plug#begin()
Plug 'preservim/NERDTree'
Plug 'vim-airline/vim-airline'
Plug 'https://github.com/ap/vim-css-color'
Plug 'vim-scripts/bash-support.vim'
Plug 'vim-vdebug/vdebug'

" Dynamically linked PncAgent Vim plugin
Plug '${REPO_ROOT}/integrations/vim'
call plug#end()

" --- UI & Editor Preferences ---
set number
hi LineNr ctermfg=white ctermbg=grey
hi CursorLineNr ctermfg=45 cterm=bold

let g:vdebug_options = { 'port' : 9003 }

" --- Navigation & Tree Keybindings ---
" Ctrl+N: Toggle NERDTree file drawer in Normal Mode
nnoremap <silent> <C-N> :NERDTreeToggle<CR>

" Tab / Shift+Tab: Quick switch between tabs in Normal Mode
nnoremap <silent> <Tab> gt
nnoremap <silent> <S-Tab> gT

" --- PncAgent Configuration ---
let g:pncagent_binary = '${BINARY_PATH}'
let g:pncagent_stream_width = 45
let g:pncagent_map_keys = 1
VIMRCEOF

echo "Updated ~/.vimrc with navigation keys and PncAgent config."

# 4. Symlink plugin to native pack directory
VIM_TARGET_DIR="${HOME}/.vim/pack/pncagent/start/pncagent"
echo "Installing Vim plugin to ${VIM_TARGET_DIR}..."
mkdir -p "${HOME}/.vim/pack/pncagent/start"
ln -sfn "${REPO_ROOT}/integrations/vim" "${VIM_TARGET_DIR}"

# 5. Non-interactively install and update all plugins
echo "Updating Vim plugins via vim-plug..."
vim -es -u "${VIMRC}" -i NONE -c "PlugInstall" -c "PlugUpdate" -c "qa" || true

echo "=================================================="
echo "Setup complete. Keybinding reference below:"
echo "=================================================="
cat << SUMMARY

File Drawer & Navigation:
-----------------------------------------------------------------------------------------
Shortcut      <Ctrl-N> (Normal Mode)     Toggles NERDTree file drawer
Shortcut      <Tab> (Normal Mode)        Next tab (equivalent to 'gt')
Shortcut      <Shift-Tab> (Normal Mode)   Previous tab (equivalent to 'gT')
Shortcut      <Ctrl-W>w                  Switch focus between split windows / panels

PncAgent Completion & Streaming:
-----------------------------------------------------------------------------------------
Shortcut      <Ctrl-L> (Insert Mode)     Inserts LLM completion directly at cursor
Shortcut      <Ctrl-K> (Normal Mode)     Toggles 45-column right-side 'pncagent stream'
Command       :PncAgentComplete          Triggers manual completion at cursor
Command       :PncAgentStream            Toggles the right-side suggestion stream
Autocommand   CursorHoldI (1.5s idle)    Silently logs predictions to .agent/logs/
-----------------------------------------------------------------------------------------
Target binary: ${BINARY_PATH}
SUMMARY

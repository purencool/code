if exists('g:loaded_pncagent')
  finish
endif
let g:loaded_pncagent = 1

command! -nargs=0 PncAgentComplete call pncagent#Complete()
command! -nargs=0 PncAgentStream call pncagent#OpenStream()

let g:pncagent_map_keys = get(g:, 'pncagent_map_keys', 1)

if g:pncagent_map_keys
  " Ctrl+L: Insert suggestion at cursor
  inoremap <silent> <C-L> <C-O>:call pncagent#Complete()<CR>
  " Ctrl+K: Open live suggestion terminal split inside Vim
  nnoremap <silent> <C-K> :call pncagent#OpenStream()<CR>
endif

" Trigger background suggestion logging when pausing for 1.5s in Insert Mode
set updatetime=1500
augroup PncAgentAutoLogs
  autocmd!
  autocmd CursorHoldI * call pncagent#AutoSuggest()
augroup END

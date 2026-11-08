if exists('g:autoloaded_pncagent')
  finish
endif
let g:autoloaded_pncagent = 1

let s:stream_bufnr = -1

function! pncagent#Complete() abort
  let l:binary = get(g:, 'pncagent_binary', 'pncagent')

  if !executable(l:binary)
    echohl WarningMsg
    echomsg '[pncagent] Binary "' . l:binary . '" not found or not executable.'
    echohl None
    return ''
  endif

  let l:line = line('.')
  let l:col = col('.') - 1
  let l:buffer_content = join(getline(1, '$'), "\n")
  let l:filename = expand('%:t')

  let l:cmd = printf('%s complete --stdin --file=%s --line=%d --col=%d',
        \ shellescape(l:binary),
        \ shellescape(l:filename),
        \ l:line,
        \ l:col)

  let l:suggestion = system(l:cmd, l:buffer_content)

  if v:shell_error != 0
    echohl WarningMsg
    let l:err_msg = !empty(l:suggestion) ? trim(substitute(l:suggestion, "\n", " ", "g")) : 'Process failed with exit code ' . v:shell_error
    echomsg '[pncagent] Error: ' . l:err_msg
    echohl None
    return ''
  endif

  if empty(l:suggestion)
    echohl WarningMsg
    echomsg '[pncagent] Warning: No completion returned from LLM.'
    echohl None
    return ''
  endif

  execute "normal! a" . l:suggestion
  return ''
endfunction

function! pncagent#AutoSuggest() abort
  let l:binary = get(g:, 'pncagent_binary', 'pncagent')
  if !executable(l:binary) || mode() !=# 'i'
    return
  endif

  let l:line = line('.')
  let l:col = col('.') - 1
  let l:buffer_content = join(getline(1, '$'), "\n")
  let l:filename = expand('%:t')

  let l:cmd = printf('%s complete --stdin --silent --file=%s --line=%d --col=%d &',
        \ shellescape(l:binary),
        \ shellescape(l:filename),
        \ l:line,
        \ l:col)

  call system(l:cmd, l:buffer_content)
endfunction

function! pncagent#OpenStream() abort
  " Toggle: kill and close stream window if already active
  if s:stream_bufnr != -1 && bufexists(s:stream_bufnr)
    let l:winid = bufwinid(s:stream_bufnr)
    if l:winid != -1
      execute 'bdelete! ' . s:stream_bufnr
      let s:stream_bufnr = -1
      return
    endif
  endif

  let l:binary = get(g:, 'pncagent_binary', 'pncagent')
  let l:width = get(g:, 'pncagent_stream_width', 45)

  if !executable(l:binary)
    echohl WarningMsg
    echomsg '[pncagent] Binary "' . l:binary . '" not found in PATH.'
    echohl None
    return
  endif

  if has('nvim')
    " Neovim vertical terminal split on the right
    execute 'botright ' . l:width . 'vnew | terminal ' . l:binary . ' stream'
    let s:stream_bufnr = bufnr('%')
    setlocal nobuflisted noswapfile nonumber norelativenumber winfixwidth
    wincmd p
  else
    " Standard Vim 8+ vertical terminal split on the right
    execute printf('botright vertical terminal ++cols=%d ++kill=term %s stream', l:width, l:binary)
    let s:stream_bufnr = bufnr('%')
    setlocal winfixwidth
    wincmd p
  endif
endfunction

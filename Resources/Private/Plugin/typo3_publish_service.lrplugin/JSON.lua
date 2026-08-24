--[[----------------------------------------------------------------------------

JSON.lua
Minimal pure-Lua JSON encoder/decoder.

The Lightroom Classic SDK does not bundle a JSON library, so this small,
dependency-free implementation is vendored with the plugin. It supports the
JSON subset needed to talk to the Lightroom Publish Service API: objects,
arrays, strings, numbers, booleans and null.

------------------------------------------------------------------------------]]

JSON = {}

local escapeMap = {
	[ '\\' ] = '\\\\',
	[ '"' ] = '\\"',
	[ '\b' ] = '\\b',
	[ '\f' ] = '\\f',
	[ '\n' ] = '\\n',
	[ '\r' ] = '\\r',
	[ '\t' ] = '\\t',
}

local function escapeString( s )
	return ( string.gsub( s, '[%z\1-\31\\"]', function( c )
		return escapeMap[ c ] or string.format( '\\u%04x', string.byte( c ) )
	end ) )
end

local function isArray( t )
	local count = 0
	for _ in pairs( t ) do
		count = count + 1
	end
	if count == 0 then
		return false -- treat empty tables as objects ({}), not arrays
	end
	for i = 1, count do
		if t[ i ] == nil then
			return false
		end
	end
	return true, count
end

local encodeValue -- forward declaration

local function encodeArray( t, count )
	local parts = {}
	for i = 1, count do
		parts[ i ] = encodeValue( t[ i ] )
	end
	return '[' .. table.concat( parts, ',' ) .. ']'
end

local function encodeObject( t )
	local parts = {}
	for key, value in pairs( t ) do
		parts[ #parts + 1 ] = '"' .. escapeString( tostring( key ) ) .. '":' .. encodeValue( value )
	end
	return '{' .. table.concat( parts, ',' ) .. '}'
end

encodeValue = function( value )
	local valueType = type( value )

	if value == nil then
		return 'null'
	elseif valueType == 'boolean' then
		return value and 'true' or 'false'
	elseif valueType == 'number' then
		return tostring( value )
	elseif valueType == 'string' then
		return '"' .. escapeString( value ) .. '"'
	elseif valueType == 'table' then
		local arrayOk, count = isArray( value )
		if arrayOk then
			return encodeArray( value, count )
		end
		return encodeObject( value )
	end

	error( 'JSON.encode: cannot encode value of type ' .. valueType )
end

function JSON.encode( value )
	return encodeValue( value )
end

-- Decoder: a small recursive-descent parser.

local function skipWhitespace( str, pos )
	local _, stop = string.find( str, '^[ \t\r\n]*', pos )
	return stop + 1
end

local decodeValue -- forward declaration

local function decodeError( str, pos, message )
	error( string.format( 'JSON.decode: %s at position %d', message, pos ) )
end

local function decodeString( str, pos )
	-- pos points at the opening quote.
	local out = {}
	local i = pos + 1
	while true do
		local c = string.sub( str, i, i )
		if c == '' then
			decodeError( str, i, 'unterminated string' )
		elseif c == '"' then
			return table.concat( out ), i + 1
		elseif c == '\\' then
			local escaped = string.sub( str, i + 1, i + 1 )
			if escaped == 'u' then
				local hex = string.sub( str, i + 2, i + 5 )
				local codepoint = tonumber( hex, 16 ) or 0
				out[ #out + 1 ] = codepoint < 128 and string.char( codepoint ) or '?'
				i = i + 6
			else
				local direct = ( {
					[ '"' ] = '"', [ '\\' ] = '\\', [ '/' ] = '/', [ 'b' ] = '\b',
					[ 'f' ] = '\f', [ 'n' ] = '\n', [ 'r' ] = '\r', [ 't' ] = '\t',
				} )[ escaped ]
				out[ #out + 1 ] = direct or escaped
				i = i + 2
			end
		else
			out[ #out + 1 ] = c
			i = i + 1
		end
	end
end

local function decodeNumber( str, pos )
	local _, stop, numberString = string.find( str, '^(-?%d+%.?%d*[eE]?[%+%-]?%d*)', pos )
	if not numberString then
		decodeError( str, pos, 'invalid number' )
	end
	return tonumber( numberString ), stop + 1
end

local function decodeArray( str, pos )
	local result = {}
	local index = 1
	pos = skipWhitespace( str, pos + 1 )
	if string.sub( str, pos, pos ) == ']' then
		return result, pos + 1
	end
	while true do
		local value
		value, pos = decodeValue( str, pos )
		result[ index ] = value
		index = index + 1
		pos = skipWhitespace( str, pos )
		local c = string.sub( str, pos, pos )
		if c == ',' then
			pos = skipWhitespace( str, pos + 1 )
		elseif c == ']' then
			return result, pos + 1
		else
			decodeError( str, pos, 'expected "," or "]"' )
		end
	end
end

local function decodeObject( str, pos )
	local result = {}
	pos = skipWhitespace( str, pos + 1 )
	if string.sub( str, pos, pos ) == '}' then
		return result, pos + 1
	end
	while true do
		pos = skipWhitespace( str, pos )
		if string.sub( str, pos, pos ) ~= '"' then
			decodeError( str, pos, 'expected string key' )
		end
		local key
		key, pos = decodeString( str, pos )
		pos = skipWhitespace( str, pos )
		if string.sub( str, pos, pos ) ~= ':' then
			decodeError( str, pos, 'expected ":"' )
		end
		pos = skipWhitespace( str, pos + 1 )
		local value
		value, pos = decodeValue( str, pos )
		result[ key ] = value
		pos = skipWhitespace( str, pos )
		local c = string.sub( str, pos, pos )
		if c == ',' then
			pos = skipWhitespace( str, pos + 1 )
		elseif c == '}' then
			return result, pos + 1
		else
			decodeError( str, pos, 'expected "," or "}"' )
		end
	end
end

decodeValue = function( str, pos )
	pos = skipWhitespace( str, pos )
	local c = string.sub( str, pos, pos )

	if c == '{' then
		return decodeObject( str, pos )
	elseif c == '[' then
		return decodeArray( str, pos )
	elseif c == '"' then
		return decodeString( str, pos )
	elseif c == 't' and string.sub( str, pos, pos + 3 ) == 'true' then
		return true, pos + 4
	elseif c == 'f' and string.sub( str, pos, pos + 4 ) == 'false' then
		return false, pos + 5
	elseif c == 'n' and string.sub( str, pos, pos + 3 ) == 'null' then
		return nil, pos + 4
	elseif c == '-' or string.find( c, '%d' ) then
		return decodeNumber( str, pos )
	end

	decodeError( str, pos, 'unexpected character "' .. c .. '"' )
end

function JSON.decode( str )
	if str == nil or str == '' then
		return nil
	end
	local value = decodeValue( str, 1 )
	return value
end
